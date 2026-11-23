<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\ShareLink;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEvent;
use App\Models\WorkflowItem;
use App\Support\Audit;
use App\Support\Notice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Runs a workflow graph. A document ("item") sits at a Task (or a Signature request) until a person
 * answers; automatic steps (email, set index, if/else, duplicate check...) run straight through.
 * Conditions are compared here as plain data - nothing stored in a graph is ever executed.
 */
class WorkflowEngine
{
    private const MAX_AUTO_STEPS = 60;

    public function start(Document $doc, Workflow $workflow, ?User $by = null): ?WorkflowItem
    {
        $start = $workflow->startNode();
        $exists = WorkflowItem::where('workflow_id', $workflow->id)->where('document_id', $doc->id)->where('status', 'active')->exists();
        if ($exists || ! $workflow->active || ! $start) {
            return null;
        }

        $item = WorkflowItem::create([
            'client_id' => $doc->client_id, 'workflow_id' => $workflow->id, 'document_id' => $doc->id,
            'started_by' => $by?->id, 'status' => 'active', 'stage_entered_at' => now(), 'node_id' => $start['id'], 'data' => [],
        ]);
        $this->event($item, $start, $by, 'started');
        Audit::log('workflow.started', $doc, "Started workflow {$workflow->name} for {$doc->original_name}", [], $doc->client_id);
        $this->follow($item, $start, 'Next', $by);

        return $item;
    }

    /** A person answers the task the item is waiting at. $forms = [formId => [field => value]]. */
    public function act(WorkflowItem $item, User $user, string $outcome, ?string $comment, array $forms = []): void
    {
        $node = $item->node();
        $doc = $item->document;
        abort_unless($item->status === 'active' && $node && $item->isAssignedTo($user), 403, 'This item is not waiting for you.');
        abort_unless(in_array($outcome, $node['config']['options'] ?? [], true), 422, 'Choose one of the listed options.');

        $this->checkRequirements($item, $node, $outcome, $forms);
        foreach ($forms as $formId => $values) {
            $form = Form::find($formId);
            if ($form && $this->formRequired($node, $outcome, (int) $formId)) {
                FormSubmission::create(['client_id' => $item->client_id, 'form_id' => $form->id, 'document_id' => $doc->id, 'item_id' => $item->id, 'user_id' => $user->id, 'data' => $values]);
            }
        }

        $this->leave($item, $node);
        $this->event($item, $node, $user, 'answered', $comment, $outcome);
        Audit::log('workflow.answered', $doc, "{$node['name']}: {$outcome}", [], $doc->client_id);
        $item->update(['escalation_level' => 0, 'data' => array_merge($item->data ?? [], ['prev_user' => $user->id, 'esc_at' => null])]);
        $this->follow($item, $node, $outcome, $user);
    }

    /** The signer answered (called by the public signing page). */
    public function signatureResult(SignatureRequest $req, string $port): void
    {
        $item = $req->item_id ? WorkflowItem::withoutGlobalScopes()->find($req->item_id) : null;
        if (! $item || $item->status !== 'active' || $item->node_id !== $req->node_id) {
            return;
        }
        $node = $item->node();
        $this->event($item, $node, null, strtolower($port), $req->signer_name);
        $this->follow($item, $node, $port, null);
    }

    /* ---------------- Walking the graph ---------------- */

    private function follow(WorkflowItem $item, array $from, string $port, ?User $by): void
    {
        $next = $item->workflow->next($from['id'], $port);
        $next ? $this->advance($item, $next, $by) : $this->finish($item, 'Ended');
    }

    private function advance(WorkflowItem $item, string $nodeId, ?User $by): void
    {
        $wf = $item->workflow;
        for ($steps = 0; $steps < self::MAX_AUTO_STEPS; $steps++) {
            $node = $wf->node($nodeId);
            $doc = $item->document;
            if (! $node || ! $doc) {
                $this->finish($item, 'Ended');

                return;
            }
            $item->update(['node_id' => $node['id']]);
            $c = $node['config'] ?? [];

            switch ($node['type']) {
                case 'task':
                    $this->enterTask($item, $node, $doc);

                    return;
                case 'signature':
                    $this->enterSignature($item, $node, $doc, $by);

                    return;
                case 'end':
                    $this->finish($item, $c['label'] ?: 'Finished', $c['archive'] ?? false);

                    return;
                case 'goto':
                    $target = Workflow::find($c['workflow_id'] ?? 0);
                    $started = $target && $target->id !== $wf->id ? $this->start($doc, $target, $by) : null;
                    $this->event($item, $node, $by, 'sent', $target?->name ?? 'unknown workflow');
                    $this->finish($item, 'Sent to '.($target?->name ?? '—'), false, ! $started && $target);

                    return;
                case 'fork':
                    $ports = \App\Support\Flow::ports($node);
                    foreach (array_slice($ports, 1) as $p) {
                        if ($to = $wf->next($node['id'], $p)) {
                            $spawn = WorkflowItem::create([
                                'client_id' => $item->client_id, 'workflow_id' => $wf->id, 'document_id' => $doc->id, 'started_by' => $item->started_by,
                                'status' => 'active', 'stage_entered_at' => now(), 'node_id' => $to, 'data' => [],
                            ]);
                            $this->event($spawn, $node, $by, 'branched', $p);
                            $this->advance($spawn, $to, $by);
                        }
                    }
                    $nodeId = $wf->next($node['id'], $ports[0]);
                    break;
                case 'condition':
                    $port = 'Otherwise';
                    foreach ($c['rules'] ?? [] as $rule) {
                        if ($this->passes($rule['conds'] ?? [], $doc)) {
                            $port = $rule['label'];
                            break;
                        }
                    }
                    $this->event($item, $node, $by, 'routed', $port);
                    $nodeId = $wf->next($node['id'], $port);
                    break;
                case 'duplicate':
                    $port = $this->isDuplicate($doc, $c['field'] ?? '') ? 'Duplicate' : 'Not a duplicate';
                    $this->event($item, $node, $by, 'checked', $port);
                    $nodeId = $wf->next($node['id'], $port);
                    break;
                case 'set_index':
                    foreach ($c['sets'] ?? [] as $s) {
                        $this->setIndex($doc, $s['field'], str_replace('{today}', today()->toDateString(), (string) $s['value']));
                    }
                    $this->event($item, $node, $by, 'set');
                    $nodeId = $wf->next($node['id'], 'Next');
                    break;
                case 'email':
                    $this->sendEmail($item, $node, $doc);
                    $nodeId = $wf->next($node['id'], 'Next');
                    break;
                default:    // start
                    $nodeId = $wf->next($node['id'], 'Next');
            }

            if (! $nodeId) {
                $this->finish($item, 'Ended');

                return;
            }
        }
        $this->finish($item, 'Stopped: too many automatic steps in a row');
    }

    private function finish(WorkflowItem $item, string $result, bool $archive = false, bool $already = false): void
    {
        $node = $item->node();
        if ($node && $node['type'] === 'task') {
            $this->leave($item, $node);
        }
        $item->update(['status' => 'complete', 'node_id' => null, 'result' => Str::limit($result, 120, ''), 'completed_at' => now()]);
        $this->event($item, null, null, $archive ? 'archived' : 'finished', $result);
        if ($item->started_by && $item->document) {
            Notice::send(User::find($item->started_by), 'Workflow finished', "{$item->document->original_name}: {$result} ({$item->workflow->name}).", route('documents.show', $item->document, false), 'circle-check', 'success');
        }
    }

    private function enterTask(WorkflowItem $item, array $node, Document $doc): void
    {
        $item->update(['stage_entered_at' => now(), 'escalation_level' => 0, 'data' => array_merge($item->data ?? [], ['esc_at' => null])]);
        if ($flag = $node['config']['flag_field'] ?? '') {
            $this->setIndex($doc, $flag, 'Yes');
        }
        $this->event($item, $node, null, 'entered');
        $this->assignees($node, $item->client_id)->each(fn ($u) => Notice::send($u, 'Waiting for you', "{$doc->original_name} is at “{$node['name']}”.", route('workflow', [], false), 'bell', 'info'));
    }

    private function leave(WorkflowItem $item, array $node): void
    {
        if (($flag = $node['config']['flag_field'] ?? '') && $item->document) {
            $this->setIndex($item->document, $flag, null);
        }
    }

    private function enterSignature(WorkflowItem $item, array $node, Document $doc, ?User $by): void
    {
        $c = $node['config'];
        $email = $c['signer_email'] ?: ($c['email_field'] ? ($doc->index_values[$c['email_field']] ?? '') : '');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->event($item, $node, $by, 'error', 'No valid signer email address. Treated as declined.');
            $this->follow($item, $node, 'Declined', $by);

            return;
        }
        $req = SignatureRequest::create([
            'client_id' => $doc->client_id, 'document_id' => $doc->id, 'item_id' => $item->id, 'requested_by' => $item->started_by, 'node_id' => $node['id'],
            'signer_name' => $c['signer_name'] ?: $email, 'signer_email' => $email, 'message' => $c['message'] ?: null, 'role' => ($c['role'] ?? '') ?: null,
            'token' => Str::random(48), 'expires_at' => now()->addDays($c['days'] ?? 14), 'remind_after_days' => (int) ($c['remind_days'] ?? 2),
        ]);
        $item->update(['stage_entered_at' => now()]);
        $signing = app(SigningService::class);
        $signing->claimBoxes($req, $doc, ! ($c['role'] ?? ''));
        $signing->activate($req, $doc);
        $this->event($item, $node, $by, 'signature_requested', $email);
    }

    public function mailSignature(SignatureRequest $req, Document $doc): void
    {
        $link = route('sign.show', $req->token);
        $this->mail([$req->signer_email], "Please sign: {$doc->original_name}", "Hello {$req->signer_name},\n\nYou have been asked to sign “{$doc->original_name}”.\n".($req->message ? "\n{$req->message}\n" : '')."\nOpen this link to review and sign (valid until {$req->expires_at->format('d M Y')}):\n{$link}\n");
    }

    private function sendEmail(WorkflowItem $item, array $node, Document $doc): void
    {
        $c = $node['config'];
        $to = [];
        foreach (preg_split('/[;,\s]+/', $c['to'] ?? '', -1, PREG_SPLIT_NO_EMPTY) as $a) {
            $to[] = $a;
        }
        if ($c['to_sender'] ?? false) {
            $to[] = (string) $doc->email_from;
        }
        if (! empty($c['to_field'])) {
            $val = (string) ($doc->index_values[$c['to_field']] ?? '');
            foreach (preg_split('/\R/', $c['to_map'] ?? '') as $line) {
                [$k, $addr] = array_pad(explode('=', $line, 2), 2, '');
                if ($val !== '' && strcasecmp(trim($k), $val) === 0) {
                    $to[] = trim($addr);
                }
            }
        }
        $to = array_values(array_unique(array_filter($to, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
        if (! $to) {
            $this->event($item, $node, null, 'email_skipped', 'No valid recipient');

            return;
        }

        $link = route('documents.show', $doc);
        if ($c['secure'] ?? false) {
            $share = ShareLink::create(['client_id' => $doc->client_id, 'document_id' => $doc->id, 'created_by' => $item->started_by, 'token' => Str::random(48), 'allow_download' => true, 'expires_at' => now()->addDays(7)]);
            $link = route('share.show', $share->token);
        }
        $body = $this->template($c['body'] ?? '', $doc, $link);
        $this->mail($to, $this->template($c['subject'] ?: 'Message about {document}', $doc, $link), $body.(($c['secure'] ?? false) ? "\n\nSecure link (expires in 7 days): {$link}" : ''));
        $this->event($item, $node, null, 'emailed', implode(', ', $to));
    }

    private function template(string $text, Document $doc, string $link): string
    {
        $text = str_replace(['{document}', '{link}', '{sender}', '{today}'], [$doc->original_name, $link, (string) $doc->email_from, today()->format('d M Y')], $text);

        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', fn ($m) => (string) ($doc->index_values[$m[1]] ?? $m[0]), $text);
    }

    private function mail(array $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* ---------------- Rules ---------------- */

    private function checkRequirements(WorkflowItem $item, array $node, string $outcome, array $forms): void
    {
        $doc = $item->document;
        $errors = [];
        foreach ($node['config']['requirements'] ?? [] as $r) {
            if ($r['on'] && ! in_array($outcome, $r['on'], true)) {
                continue;
            }
            if ($r['kind'] === 'field') {
                $v = $doc->index_values[$r['value']] ?? null;
                if ($v === null || $v === '') {
                    $label = $doc->project?->fields->firstWhere('key', $r['value'])?->label ?? $r['value'];
                    $errors['requirements'][] = "“{$label}” must be filled in (index values) before choosing “{$outcome}”.";
                }
            } elseif ($r['kind'] === 'doc') {
                if (! $doc->linked()->contains(fn ($d) => strcasecmp((string) $d->doc_type, $r['value']) === 0)) {
                    $errors['requirements'][] = "A “{$r['value']}” document must be added to this job before choosing “{$outcome}”.";
                }
            } elseif ($r['kind'] === 'form') {
                $form = Form::find((int) $r['value']);
                if ($form) {
                    $names = collect($form->fields)->mapWithKeys(fn ($f) => ["form.{$form->id}.{$f['key']}" => "“{$f['label']}”"])->all();
                    $v = Validator::make(['form' => [$form->id => $forms[$form->id] ?? []]], $form->rulesFor("form.{$form->id}"), [], $names);
                    foreach ($v->errors()->all() as $m) {
                        $errors['requirements'][] = "{$form->name}: {$m}";
                    }
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function formRequired(array $node, string $outcome, int $formId): bool
    {
        foreach ($node['config']['requirements'] ?? [] as $r) {
            if ($r['kind'] === 'form' && (int) $r['value'] === $formId && (! $r['on'] || in_array($outcome, $r['on'], true))) {
                return true;
            }
        }

        return false;
    }

    public function assignees(array $node, int $clientId): Collection
    {
        $ids = $node['config']['assignees'] ?? [];

        return $ids
            ? User::where('client_id', $clientId)->whereIn('id', $ids)->where('status', 'active')->get()
            : Notice::clientAdmins($clientId);
    }

    private function isDuplicate(Document $doc, string $field): bool
    {
        $v = $field ? trim((string) ($doc->index_values[$field] ?? '')) : '';
        if ($v === '') {
            return false;
        }

        return Document::where('client_id', $doc->client_id)->where('project_id', $doc->project_id)->where('id', '!=', $doc->id)
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(index_values, ?)) = ?', ['$."'.str_replace('"', '', $field).'"', $v])->exists();
    }

    private function setIndex(Document $doc, string $key, ?string $value): void
    {
        $vals = $doc->index_values ?? [];
        $vals[$key] = $value === '' ? null : $value;
        $doc->index_values = $vals;
        $doc->applyCalculated();
        $doc->refreshSearchText();
        $doc->saveQuietly();
    }

    /** All conditions must be true. */
    public function passes(array $conds, Document $doc): bool
    {
        foreach ($conds as $c) {
            if (! $this->compare($doc->index_values[$c['field']] ?? null, $c['op'] ?? 'eq', (string) ($c['value'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    private function compare($actual, string $op, string $expected): bool
    {
        $a = $actual === null ? '' : trim((string) $actual);
        $numeric = is_numeric($a) && is_numeric($expected);

        return match ($op) {
            'empty' => $a === '',
            'filled' => $a !== '',
            'eq' => $numeric ? (float) $a == (float) $expected : strcasecmp($a, $expected) === 0,
            'ne' => $numeric ? (float) $a != (float) $expected : strcasecmp($a, $expected) !== 0,
            'gt' => $numeric ? (float) $a > (float) $expected : ($a !== '' && strcmp($a, $expected) > 0),
            'gte' => $numeric ? (float) $a >= (float) $expected : ($a !== '' && strcmp($a, $expected) >= 0),
            'lt' => $numeric ? (float) $a < (float) $expected : ($a !== '' && strcmp($a, $expected) < 0),
            'lte' => $numeric ? (float) $a <= (float) $expected : ($a !== '' && strcmp($a, $expected) <= 0),
            'contains' => $expected !== '' && stripos($a, $expected) !== false,
            default => false,
        };
    }

    private function event(WorkflowItem $item, ?array $node, ?User $user, string $action, ?string $comment = null, ?string $outcome = null): void
    {
        WorkflowEvent::create([
            'client_id' => $item->client_id, 'item_id' => $item->id, 'node_id' => $node['id'] ?? null, 'node_name' => $node['name'] ?? null,
            'user_id' => $user?->id, 'action' => $action, 'comment' => $comment ? Str::limit($comment, 1000, '') : null, 'outcome' => $outcome,
        ]);
    }

    /* ---------------- Reminders ---------------- */

    /** Overdue tasks: remind assignees, then the named manager (or the previous person), then flag to admins. */
    public function escalate(): int
    {
        $n = 0;
        $items = WorkflowItem::withoutGlobalScopes()->where('status', 'active')->whereNotNull('node_id')->with(['document', 'workflow'])->get();
        foreach ($items as $item) {
            if (! $item->isOverdue() || $item->escalation_level >= 3) {
                continue;
            }
            $node = $item->node();
            $level = $item->escalation_level + 1;
            $name = $item->document?->original_name ?? 'A document';
            $msg = "{$name} has waited at “{$node['name']}” for over {$node['config']['overdue_hours']} hours.";
            $url = route('workflow', [], false);
            if ($level === 1) {
                Notice::send($this->assignees($node, $item->client_id), 'Overdue: please action', $msg, $url, 'triangle-alert', 'warning');
            } elseif ($level === 2) {
                $esc = $node['config']['escalate_to'] ?? '';
                $who = $esc === 'previous' ? User::find($item->data['prev_user'] ?? 0) : ($esc ? User::where('client_id', $item->client_id)->find((int) $esc) : null);
                Notice::send($who ?: Notice::clientAdmins($item->client_id), 'Overdue item escalated to you', $msg, $url, 'triangle-alert', 'danger');
            } else {
                Notice::send(Notice::clientAdmins($item->client_id), 'Overdue item flagged', $msg, $url, 'triangle-alert', 'danger');
            }
            $item->update(['escalation_level' => $level, 'data' => array_merge($item->data ?? [], ['esc_at' => now()->toIso8601String()])]);
            $this->event($item, $node, null, 'escalated', "Level {$level}");
            $n++;
        }

        return $n;
    }
}

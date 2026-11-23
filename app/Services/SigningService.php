<?php

namespace App\Services;

use App\Jobs\ProcessDocumentOcr;
use App\Models\Annotation;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\Notice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Everything about getting a document signed by one or many people: packets, order, signing into the file, reminders. */
class SigningService
{
    public const MAX_REMINDERS = 3;

    private function disk()
    {
        return Storage::disk(config('filesystems.documents_disk'));
    }

    /**
     * One request per signer. With "in order", only the first is sent now; each next one is sent when the one before has signed.
     *
     * @param  array<int, array{role?:?string,name:string,email:string,user_id?:?int}>  $signers
     * @param  array{message?:?string,days?:int,in_order?:bool,remind_days?:int}  $opts
     * @return array<int, SignatureRequest>
     */
    public function createPacket(Document $doc, array $signers, ?User $by, array $opts = []): array
    {
        $inOrder = (bool) ($opts['in_order'] ?? true);
        $days = (int) ($opts['days'] ?? 14);
        $made = [];
        foreach (array_values($signers) as $i => $s) {
            $role = trim((string) ($s['role'] ?? '')) ?: null;
            $req = SignatureRequest::withoutGlobalScopes()->create([
                'client_id' => $doc->client_id, 'document_id' => $doc->id, 'requested_by' => $by?->id,
                'signer_name' => $s['name'], 'signer_email' => $s['email'], 'signer_user_id' => $s['user_id'] ?? null, 'role' => $role,
                'seq' => $inOrder ? $i + 1 : 0, 'status' => ($inOrder && $i > 0) ? 'queued' : 'pending',
                'message' => $opts['message'] ?? null, 'token' => Str::random(48), 'expires_at' => now()->addDays($days),
                'remind_after_days' => (int) ($opts['remind_days'] ?? 2),
            ]);
            $this->claimBoxes($req, $doc, count($signers) === 1);
            $made[] = $req;
        }
        foreach ($made as $req) {
            $req->status === 'pending' && $this->activate($req, $doc);
        }

        return $made;
    }

    /** Boxes drawn with this role become the signer's. A lone signer with no role takes every free box. */
    public function claimBoxes(SignatureRequest $req, Document $doc, bool $lone): int
    {
        $q = Annotation::withoutGlobalScopes()->where('document_id', $doc->id)->where('kind', 'sign')->whereNull('signature_request_id')->whereNull('applied_at');
        if ($req->role) {
            $q->where('text', $req->role);
        } elseif (! $lone) {
            return 0;
        }

        return $q->update(['signature_request_id' => $req->id]);
    }

    /** Sends the signing link now. */
    public function activate(SignatureRequest $req, ?Document $doc = null): void
    {
        $doc ??= Document::withoutGlobalScopes()->find($req->document_id);
        $req->forceFill(['status' => 'pending', 'activated_at' => now()])->save();
        app(WorkflowEngine::class)->mailSignature($req, $doc);
        if ($req->signer_user_id && ($u = User::withoutGlobalScopes()->find($req->signer_user_id))) {
            Notice::send($u, 'Please sign', "{$doc->original_name} is waiting for your signature.", route('sign.show', $req->token, false), 'pen-line', 'info');
        }
        Audit::log('signature.sent', $doc, "Signature requested from {$req->signer_name}".($req->role ? " ({$req->role})" : '')." for {$doc->original_name}", ['email' => $req->signer_email], $doc->client_id);
    }

    /** After someone signs, the next person in the order gets their link. */
    public function afterSigned(SignatureRequest $req): ?SignatureRequest
    {
        $next = SignatureRequest::withoutGlobalScopes()->where('document_id', $req->document_id)->where('status', 'queued')->orderBy('seq')->orderBy('id')->first();
        if ($next) {
            $this->activate($next);
        } else {
            $doc = Document::withoutGlobalScopes()->find($req->document_id);
            $open = SignatureRequest::withoutGlobalScopes()->where('document_id', $req->document_id)->where('status', 'pending')->count();
            if (! $open && $req->requested_by && $doc) {
                Notice::send(User::find($req->requested_by), 'Everyone has signed', "All signatures on {$doc->original_name} are complete.", route('documents.show', $doc, false), 'circle-check', 'success');
                Audit::log('signature.completed', $doc, "All signatures on {$doc->original_name} are complete", [], $doc->client_id);
            }
        }

        return $next;
    }

    /** Someone declined: the people still waiting their turn are cancelled. */
    public function cancelQueued(SignatureRequest $req): void
    {
        SignatureRequest::withoutGlobalScopes()->where('document_id', $req->document_id)->where('status', 'queued')->update(['status' => 'cancelled']);
    }

    /** Puts this signer's signature into their boxes. The file becomes a new version (the earlier file is kept), so the next signer sees it. */
    public function applyToDocument(SignatureRequest $req, string $dataUrl): bool
    {
        $png = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if ($png === false) {
            return false;
        }
        try {
            return DB::transaction(function () use ($req, $png) {
                $doc = Document::withoutGlobalScopes()->lockForUpdate()->find($req->document_id);
                // Boxes drawn after the request was created (for example after a workflow started) are picked up now
                if ($doc && $req->role) {
                    $this->claimBoxes($req, $doc, false);
                }
                $marks = Annotation::withoutGlobalScopes()->where('signature_request_id', $req->id)->where('kind', 'sign')->whereNull('applied_at')->get();
                $pages = app(PageService::class);
                if (! $doc || $marks->isEmpty() || ! $pages->available($doc)) {
                    return false;
                }
                $boxes = [];
                foreach ($marks as $m) {
                    $boxes[$m->page][] = [$m->x, $m->y, $m->w, $m->h];
                }
                [$bytes, $mime] = $pages->redact($doc, $boxes, $png);
                $ext = $mime === 'application/pdf' ? 'pdf' : (str_contains($mime, 'jpeg') ? 'jpg' : 'png');
                $path = "clients/{$doc->client_id}/projects/{$doc->project_id}/".Str::uuid().".$ext";
                $this->disk()->put($path, $bytes);
                $next = (DocumentVersion::withoutGlobalScopes()->where('document_id', $doc->id)->max('version_no') ?? 0) + 1;
                DocumentVersion::withoutGlobalScopes()->create([
                    'client_id' => $doc->client_id, 'document_id' => $doc->id, 'version_no' => $next, 'path' => $doc->path, 'mime' => $doc->mime, 'size' => $doc->size,
                    'uploaded_by' => $doc->uploaded_by, 'note' => 'Before '.$req->signer_name.' signed', 'replaced_at' => now(),
                ]);
                $doc->update(['path' => $path, 'mime' => $mime, 'size' => strlen($bytes), 'ocr_status' => 'none', 'ocr_text' => null]);
                $marks->each->update(['applied_at' => now()]);
                $doc->project?->ocr_enabled && ProcessDocumentOcr::dispatchAfterResponse($doc->id);

                return true;
            });
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** A separate copy of the document (with its Sign-here boxes) for one recipient. */
    public function cloneFor(Document $doc, string $recipientName): Document
    {
        $ext = strtolower(pathinfo($doc->path, PATHINFO_EXTENSION)) ?: 'pdf';
        $path = "clients/{$doc->client_id}/projects/{$doc->project_id}/".Str::uuid().".$ext";
        $this->disk()->copy($doc->path, $path);
        $base = pathinfo($doc->original_name, PATHINFO_FILENAME);
        $copy = Document::withoutGlobalScopes()->create([
            'client_id' => $doc->client_id, 'project_id' => $doc->project_id, 'uploaded_by' => $doc->uploaded_by,
            'original_name' => Str::limit($base, 120, '').' - '.Str::limit($recipientName, 40, '').'.'.pathinfo($doc->original_name, PATHINFO_EXTENSION),
            'mime' => $doc->mime, 'size' => $doc->size, 'path' => $path, 'status' => $doc->status, 'index_values' => $doc->index_values, 'doc_type' => $doc->doc_type,
            'ocr_status' => $doc->ocr_status, 'ocr_text' => $doc->ocr_text,
        ]);
        foreach (Annotation::withoutGlobalScopes()->where('document_id', $doc->id)->where('kind', 'sign')->whereNull('signature_request_id')->whereNull('applied_at')->get() as $a) {
            Annotation::withoutGlobalScopes()->create($a->only(['client_id', 'user_id', 'kind', 'page', 'x', 'y', 'w', 'h', 'text']) + ['document_id' => $copy->id]);
        }

        return $copy;
    }

    /** A nudge to someone who has not signed. Returns false when it was not sent. */
    public function remind(SignatureRequest $req, bool $manual = false): bool
    {
        if ($req->status !== 'pending' || $req->expires_at->isPast()) {
            return false;
        }
        $doc = Document::withoutGlobalScopes()->find($req->document_id);
        if (! $doc) {
            return false;
        }
        $seen = $req->view_count > 0;
        $link = route('sign.show', $req->token);
        $body = "Hello {$req->signer_name},\n\nThis is a reminder: “{$doc->original_name}” is still waiting for your signature.\n"
            .($seen ? "You opened it but have not signed yet.\n" : "You have not opened it yet.\n")
            ."\nOpen this link to review and sign (valid until {$req->expires_at->format('d M Y')}):\n{$link}\n";
        try {
            Mail::raw($body, fn ($m) => $m->to($req->signer_email)->subject("Reminder: please sign {$doc->original_name}"));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
        $req->forceFill(['reminded_at' => now(), 'reminder_count' => $req->reminder_count + 1])->save();
        if ($req->signer_user_id && ($u = User::withoutGlobalScopes()->find($req->signer_user_id))) {
            Notice::send($u, 'Reminder: please sign', "{$doc->original_name} is still waiting for your signature.", route('sign.show', $req->token, false), 'pen-line', 'warning');
        }
        Audit::log('signature.reminded', $doc, ($manual ? 'Reminder sent' : 'Automatic reminder sent')." to {$req->signer_name} for {$doc->original_name} (".($seen ? 'opened, not signed' : 'not opened').')', ['count' => $req->reminder_count], $doc->client_id);
        if (! $manual && $req->reminder_count >= self::MAX_REMINDERS && $req->requested_by && ($by = User::withoutGlobalScopes()->find($req->requested_by))) {
            Notice::send($by, 'Still not signed', "{$req->signer_name} has not signed {$doc->original_name} after {$req->reminder_count} reminders.", route('documents.show', $doc, false), 'triangle-alert', 'warning');
        }

        return true;
    }

    /** Called by the scheduler: reminds everyone whose turn it is and who has been quiet for their reminder interval. */
    public function sendDueReminders(): int
    {
        $n = 0;
        $q = SignatureRequest::withoutGlobalScopes()->where('status', 'pending')->whereNotNull('activated_at')->where('expires_at', '>', now())->where('reminder_count', '<', self::MAX_REMINDERS);
        foreach ($q->get() as $req) {
            $last = $req->reminded_at ?? $req->activated_at;
            if ($last->copy()->addDays(max(1, (int) $req->remind_after_days))->isPast() && $this->remind($req)) {
                $n++;
            }
        }

        return $n;
    }
}

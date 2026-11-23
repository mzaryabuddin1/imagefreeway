<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentOcr;
use App\Models\DocType;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use App\Support\Audit;
use App\Support\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Emailed documents. Your mail provider (Mailgun, SendGrid, Postmark, an IMAP bridge...) posts each
 * incoming message here: POST /inbound/{alias} with from, subject and attachments[], and the header
 * X-Inbound-Secret. The alias picks the document type; the contract number is read from the subject.
 */
class InboundEmailController extends Controller
{
    public function receive(Request $request, string $alias, WorkflowEngine $engine)
    {
        $secret = (string) config('services.inbound.secret');
        abort_if($secret === '', 503, 'Inbound email is not switched on.');
        abort_unless(hash_equals($secret, (string) $request->header('X-Inbound-Secret')), 403);

        $type = DocType::withoutGlobalScopes()->where('email_alias', strtolower($alias))->first();
        abort_unless($type && $type->project_id, 404);

        $data = $request->validate([
            'from' => 'required|string|max:190', 'subject' => 'nullable|string|max:300',
            'attachments' => 'required|array|min:1|max:20',
            'attachments.*' => ['file', 'max:102400', 'extensions:'.implode(',', DocumentController::EXTENSIONS)],
        ]);
        $sender = $this->address($data['from']);
        $project = Project::withoutGlobalScopes()->with('fields')->find($type->project_id);
        $number = $type->extractNumber((string) ($data['subject'] ?? ''));
        $field = $type->index_field ?: $project->link_field;

        if (! $number) {
            $this->reply($sender, 'We could not file your document', "Hello,\n\nYour email “{$data['subject']}” reached us, but we could not find a contract number in the subject line, so the document was not filed.\nPlease send it again with the number in the subject.");
            Notice::send(Notice::clientAdmins($type->client_id), 'Emailed document rejected', "From {$sender}: no number found in the subject “{$data['subject']}”.", route('documents', [], false), 'triangle-alert', 'warning');
            Audit::log('email.rejected', null, "Emailed document from {$sender} rejected: no number in subject", [], $type->client_id);

            return response()->json(['ok' => false, 'message' => 'No contract number found in the subject.'], 422);
        }

        $disk = Storage::disk(config('filesystems.documents_disk'));
        $created = [];
        $quarantined = 0;
        foreach ($request->file('attachments') as $file) {
            if (app(\App\Services\VirusScanner::class)->check($file, $project->client_id, $project->id, null, 'email', $sender)) {
                $quarantined++;

                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            $name = Str::limit(preg_replace('/[^\pL\pN\s._()\-&]+/u', '', $file->getClientOriginalName()) ?: "file.$ext", 180, '');
            $values = $field ? [$field => $number] : [];
            $doc = Document::withoutGlobalScopes()->create([
                'client_id' => $project->client_id, 'project_id' => $project->id, 'original_name' => $name,
                'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'ocr_status' => 'none',
                'path' => $disk->putFileAs("clients/{$project->client_id}/projects/{$project->id}", $file, Str::uuid().'.'.$ext),
                'doc_type' => $type->name, 'email_from' => $sender, 'index_values' => $values,
            ]);
            $missing = $project->fields->filter(fn ($f) => $f->required && empty($values[$f->key]))->isNotEmpty();
            $doc->status = $missing ? 'unindexed' : 'indexed';
            $doc->refreshSearchText();
            $doc->recomputeDisposal($project);
            $doc->saveQuietly();
            Audit::log('document.emailed', $doc, "Emailed in by {$sender} as {$type->name} ({$number})", [], $project->client_id);
            $project->ocr_enabled && ProcessDocumentOcr::dispatchAfterResponse($doc->id);

            if ($type->workflow_id && ($wf = Workflow::withoutGlobalScopes()->find($type->workflow_id))) {
                $engine->start($doc, $wf, null);
            }
            $created[] = $doc->id;
        }
        $created && Notice::send(Notice::clientAdmins($project->client_id), 'Document emailed in', count($created)." {$type->name} file(s) from {$sender}, number {$number}.", route('documents', [], false), 'mail', 'info');
        $quarantined && $this->reply($sender, 'A file was not accepted', "Hello,\n\nOne or more files in your email “{$data['subject']}” failed our virus scan and were not filed.");

        return response()->json(['ok' => true, 'number' => $number, 'type' => $type->name, 'documents' => $created, 'quarantined' => $quarantined]);
    }

    private function address(string $from): string
    {
        return preg_match('/[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+/', $from, $m) ? strtolower($m[0]) : $from;
    }

    private function reply(string $to, string $subject, string $body): void
    {
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

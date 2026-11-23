<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentOcr;
use App\Models\Annotation;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Services\PageService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnotationController extends Controller
{
    private function allow(Document $document, string $right): void
    {
        abort_unless(auth()->user()->canOn($document->project, $right), 403, 'You do not have permission to do this in this project.');
    }

    /** Page count and every mark, for the markup viewer. */
    public function pages(Document $document, PageService $pages)
    {
        $this->allow($document, 'can_view');
        if (! $pages->available($document)) {
            return response()->json(['available' => false, 'message' => $document->isPdf() ? 'PDF pages need poppler (pdftoppm) on the server.' : 'Only PDF and image files can be marked up.']);
        }
        $marks = Annotation::where('document_id', $document->id)->whereNull('applied_at')->with('author')->orderBy('id')->get()
            ->map(fn ($a) => $this->row($a));

        return response()->json([
            'available' => true,
            'pages' => $pages->pageCount($document),
            'marks' => $marks,
            'can_redact' => auth()->user()->canOn($document->project, 'can_upload'),
        ]);
    }

    public function page(Document $document, int $n, PageService $pages)
    {
        $this->allow($document, 'can_view');
        abort_if($n < 1 || $n > PageService::MAX_PAGES, 404);
        $img = $pages->page($document, $n);
        abort_unless($img, 404, 'This page could not be shown.');

        return response($img[0], 200, ['Content-Type' => $img[1], 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function store(Request $request, Document $document)
    {
        $kind = $request->input('kind');
        $this->allow($document, in_array($kind, ['redact', 'sign'], true) ? 'can_upload' : 'can_view');
        $d = $request->validate([
            'kind' => 'required|in:note,highlight,redact,sign',
            'page' => 'required|integer|min:1|max:'.PageService::MAX_PAGES,
            'x' => 'required|numeric|between:0,1', 'y' => 'required|numeric|between:0,1',
            'w' => 'required|numeric|between:0.002,1', 'h' => 'required|numeric|between:0.002,1',
            'text' => 'nullable|string|max:500',
        ]);
        abort_if($d['kind'] === 'note' && trim((string) ($d['text'] ?? '')) === '', 422, 'Write the note first.');
        abort_if($d['kind'] === 'sign' && trim((string) ($d['text'] ?? '')) === '', 422, 'Say who signs here (for example Candidate or HR Manager).');
        if ($d['kind'] === 'sign') {
            $d['text'] = mb_substr(trim($d['text']), 0, 60);
        }
        $d['w'] = min($d['w'], 1 - $d['x']);
        $d['h'] = min($d['h'], 1 - $d['y']);

        $a = Annotation::create($d + ['client_id' => $document->client_id, 'document_id' => $document->id, 'user_id' => $request->user()->id]);
        Audit::log('document.annotated', $document, ucfirst($d['kind'])." added on page {$d['page']} of {$document->original_name}", [], $document->client_id);

        return response()->json(['ok' => true, 'mark' => $this->row($a->load('author'))]);
    }

    public function destroy(Annotation $annotation)
    {
        $document = $annotation->document;
        $this->allow($document, 'can_view');
        abort_unless(auth()->user()->isAdmin() || $annotation->user_id === auth()->id(), 403, 'You can only remove your own marks.');
        $annotation->delete();

        return response()->json(['ok' => true]);
    }

    /** Builds a NEW document with every pending redaction burned in. The original is left as it is. */
    public function redact(Request $request, Document $document, PageService $pages)
    {
        $this->allow($document, 'can_upload');
        $marks = Annotation::where('document_id', $document->id)->where('kind', 'redact')->whereNull('applied_at')->get();
        abort_if($marks->isEmpty(), 422, 'Draw at least one redaction box first.');

        $boxes = [];
        foreach ($marks as $m) {
            $boxes[$m->page][] = [$m->x, $m->y, $m->w, $m->h];
        }
        [$bytes, $mime, $ext] = $pages->redact($document, $boxes);

        $project = $document->project;
        $path = "clients/{$document->client_id}/projects/{$project->id}/".Str::uuid().".$ext";
        Storage::disk(config('filesystems.documents_disk'))->put($path, $bytes);
        $base = pathinfo($document->original_name, PATHINFO_FILENAME);

        $copy = Document::create([
            'client_id' => $document->client_id, 'project_id' => $project->id, 'uploaded_by' => $request->user()->id,
            'original_name' => Str::limit($base, 150, '').' (redacted).'.$ext, 'mime' => $mime, 'size' => strlen($bytes), 'path' => $path,
            'status' => $document->status, 'index_values' => $document->index_values, 'doc_type' => $document->doc_type, 'ocr_status' => 'none',
        ]);
        $copy->recomputeDisposal($project);
        $copy->saveQuietly();
        $marks->each->update(['applied_at' => now()]);
        $project->ocr_enabled && ProcessDocumentOcr::dispatchAfterResponse($copy->id);
        Audit::log('document.redacted', $document, "Redacted copy made from {$document->original_name} ({$marks->count()} area(s))", [], $document->client_id);

        return $this->okJson("Redacted copy created: {$copy->original_name}", route('documents.show', $copy));
    }

    private function okJson(string $message, string $redirect)
    {
        return response()->json(['ok' => true, 'message' => $message, 'redirect' => $redirect]);
    }

    private function row(Annotation $a): array
    {
        return ['id' => $a->id, 'kind' => $a->kind, 'page' => $a->page, 'x' => $a->x, 'y' => $a->y, 'w' => $a->w, 'h' => $a->h,
            'text' => $a->text, 'signer' => $a->signature_request_id ? SignatureRequest::withoutGlobalScopes()->find($a->signature_request_id)?->signer_name : null, 'by' => $a->author?->name ?? 'Someone', 'mine' => $a->user_id === auth()->id() || auth()->user()->isAdmin(), 'at' => $a->created_at?->format('d M H:i')];
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Jobs\ProcessDocumentOcr;
use App\Models\DocType;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\SignatureRequest;
use App\Models\Project;
use App\Models\ShareLink;
use App\Models\Workflow;
use App\Models\WorkflowItem;
use App\Services\VirusScanner;
use App\Services\WorkflowEngine;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    use Responds;

    /** Video and large media are off by default (F-ING-02), so they are not on this list. */
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'png', 'jpg', 'jpeg', 'gif', 'bmp', 'tif', 'tiff', 'webp'];

    private function disk()
    {
        return Storage::disk(config('filesystems.documents_disk'));
    }

    private function allow(Project $project, string $right = 'can_view'): void
    {
        abort_unless(auth()->user()->canOn($project, $right), 403, 'You do not have permission to do this in this project.');
    }

    /* ---------------- Browse and search ---------------- */

    public function index(Request $request)
    {
        $user = $request->user();
        $projects = $user->projectsWith('can_view')->orderBy('name')->get()->groupBy(fn ($p) => $p->department->name);
        $stats = Document::whereIn('project_id', $projects->flatten()->pluck('id'))
            ->selectRaw('project_id, count(*) total, sum(status = "unindexed") pending')->groupBy('project_id')->get()->keyBy('project_id');

        $all = $projects->flatten();
        $depts = $all->groupBy('department_id')->map(fn ($list) => (object) [
            'id' => $list->first()->department_id, 'name' => $list->first()->department->name, 'projects' => $list,
            'docs' => $list->sum(fn ($p) => (int) ($stats[$p->id]->total ?? 0)),
        ])->sortBy('name')->values();

        $current = $all->firstWhere('id', (int) $request->query('project'));
        $dept = $current ? $depts->firstWhere('id', $current->department_id) : $depts->firstWhere('id', (int) $request->query('dept'));
        $files = collect();
        $total = 0;
        if ($current) {
            $q = Document::where('project_id', $current->id)->with('uploader');
            $total = (clone $q)->count();
            $files = $q->orderBy('original_name')->limit(300)->get();
        }

        return view('documents.index', compact('depts', 'dept', 'stats', 'current', 'files', 'total'));
    }

    public function project(Request $request, Project $project)
    {
        $this->allow($project);
        $project->load('department', 'fields');
        $fields = $project->fields;

        $query = Document::where('project_id', $project->id)->with('uploader');
        $this->applyFilters($query, $request, $fields);

        $sort = (string) $request->query('sort', 'created');
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $field = $fields->firstWhere('key', $sort);
        if ($field) {
            $expr = $this->expr($field->key);
            $query->orderByRaw(in_array($field->type, ['number', 'amount'], true) ? "CAST($expr AS DECIMAL(20,4)) $dir" : "$expr $dir");
        } else {
            $query->orderBy(['name' => 'original_name', 'size' => 'size', 'status' => 'status'][$sort] ?? 'created_at', $dir);
        }

        $documents = $query->paginate(25)->withQueryString();
        $pending = Document::where('project_id', $project->id)->where('status', 'unindexed')->count();

        return view('documents.project', compact('project', 'fields', 'documents', 'pending', 'sort', 'dir'));
    }

    /** Client Admin: every document of a project as picture cards, with a version stack on those that have older files. */
    public function gallery(Request $request, Project $project)
    {
        $this->allow($project);
        $project->load('department', 'fields');
        $query = Document::where('project_id', $project->id);
        $this->applyFilters($query, $request, $project->fields);
        $documents = $query->orderByDesc('created_at')->paginate(24)->withQueryString();
        $versions = DocumentVersion::whereIn('document_id', $documents->pluck('id'))->orderByDesc('version_no')->get()->groupBy('document_id');
        $canDownload = auth()->user()->canOn($project, 'can_download');

        return view('documents.gallery', compact('project', 'documents', 'versions', 'canDownload'));
    }

    private function expr(string $key): string
    {
        abort_unless(preg_match('/^[a-z0-9_]+$/', $key), 400);

        return "JSON_UNQUOTE(JSON_EXTRACT(index_values, '$.\"$key\"'))";
    }

    private function applyFilters($query, Request $request, $fields): void
    {
        if ($n = trim((string) $request->query('name'))) {
            $query->where('original_name', 'like', "%$n%");
        }
        if (in_array($s = $request->query('status'), ['indexed', 'unindexed'], true)) {
            $query->where('status', $s);
        }
        if ($t = trim((string) $request->query('text'))) {
            $query->where(fn ($w) => $w->where('ocr_text', 'like', "%$t%")->orWhere('search_text', 'like', "%$t%"));
        }
        foreach ($fields->where('searchable', true) as $f) {
            $expr = $this->expr($f->key);
            $v = trim((string) $request->input("f.{$f->key}", ''));
            $from = trim((string) $request->input("from.{$f->key}", ''));
            $to = trim((string) $request->input("to.{$f->key}", ''));

            if (in_array($f->type, ['date', 'number', 'amount'], true)) {
                $col = $f->type === 'date' ? $expr : "CAST($expr AS DECIMAL(20,4))";
                $from !== '' && $query->whereRaw("$col >= ?", [$from]);
                $to !== '' && $query->whereRaw("$col <= ?", [$to]);
            } elseif ($v !== '') {
                in_array($f->type, ['yesno', 'picklist'], true)
                    ? $query->whereRaw("$expr = ?", [$v])
                    : $query->whereRaw("$expr like ?", ["%$v%"]);
            }
        }
    }

    public function search(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));
        $results = collect();

        if ($q !== '') {
            $ids = $user->projectsWith('can_view')->pluck('id');   // permissions applied inside the search, so counts are right (F-SRCH-02)
            $terms = preg_split('/\s+/', $q);
            $query = Document::whereIn('project_id', $ids)->with('project.department');
            foreach ($terms as $term) {
                $query->where(fn ($w) => $w->where('original_name', 'like', "%$term%")->orWhere('search_text', 'like', "%$term%")->orWhere('ocr_text', 'like', "%$term%"));
            }
            $results = $query->latest()->paginate(25)->withQueryString();
            $results->getCollection()->transform(function ($d) use ($terms) {
                $d->snippet = $this->snippet($d->ocr_text, $terms);

                return $d;
            });
        }

        return view('documents.search', compact('q', 'results'));
    }

    /** Search-as-you-type: a few documents and projects the user may see, best name matches first. */
    public function suggest(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['docs' => [], 'projects' => [], 'total' => 0]);
        }
        $projects = $user->projectsWith('can_view')->with('department')->get();
        $terms = array_slice(preg_split('/\s+/', $q), 0, 5);

        $query = Document::whereIn('project_id', $projects->pluck('id'));
        foreach ($terms as $term) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($w) => $w->where('original_name', 'like', $like)->orWhere('search_text', 'like', $like)->orWhere('ocr_text', 'like', $like));
        }
        $total = (clone $query)->count();
        $docs = $query->orderByRaw('original_name like ? desc', [addcslashes($terms[0], '%_\\').'%'])->orderByRaw('original_name like ? desc', ['%'.addcslashes($terms[0], '%_\\').'%'])->latest()->limit(6)->get()
            ->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->original_name, 'url' => route('documents.show', $d), 'project' => $projects->firstWhere('id', $d->project_id)?->name,
                'type' => $d->doc_type, 'status' => $d->status, 'ext' => strtolower(pathinfo($d->original_name, PATHINFO_EXTENSION)),
                'snippet' => str_contains(mb_strtolower($d->original_name), mb_strtolower($terms[0])) ? null : ($this->snippet($d->ocr_text, $terms) ?? $this->snippet($d->search_text, $terms)),
            ])->values();

        $matched = $projects->filter(fn ($p) => collect($terms)->every(fn ($t) => mb_stripos($p->name, $t) !== false))->take(3)
            ->map(fn ($p) => ['name' => $p->name, 'dept' => $p->department?->name, 'url' => route('projects.show', $p)])->values();

        return response()->json(['docs' => $docs, 'projects' => $matched, 'total' => $total, 'see_all' => route('search', ['q' => $q])]);
    }

    private function snippet(?string $text, array $terms): ?string
    {
        if (! $text) {
            return null;
        }
        foreach ($terms as $t) {
            $pos = mb_stripos($text, $t);
            if ($pos !== false) {
                $s = max(0, $pos - 50);

                return ($s > 0 ? '…' : '').trim(preg_replace('/\s+/', ' ', mb_substr($text, $s, 140))).'…';
            }
        }

        return null;
    }

    /* ---------------- Upload ---------------- */

    public function upload(Request $request, Project $project)
    {
        $this->allow($project, 'can_upload');
        $request->validate(['file' => ['required', 'file', 'max:102400', 'extensions:'.implode(',', self::EXTENSIONS)]],
            ['file.extensions' => 'This file type is not allowed. Allowed: '.implode(', ', self::EXTENSIONS).'.', 'file.max' => 'Files can be up to 100 MB.']);

        $file = $request->file('file');
        if ($held = app(VirusScanner::class)->check($file, $project->client_id, $project->id, $request->user()->id, 'upload')) {
            return response()->json(['ok' => false, 'message' => "{$held->original_name} failed the virus scan ({$held->threat}) and was held in quarantine. It was not filed."], 422);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $name = Str::limit(preg_replace('/[^\pL\pN\s._()\-&]+/u', '', $file->getClientOriginalName()) ?: "file.$ext", 180, '');
        $path = $this->disk()->putFileAs("clients/{$project->client_id}/projects/{$project->id}", $file, Str::uuid().'.'.$ext);

        $doc = Document::create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $name,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
            'status' => $project->fields()->exists() ? 'unindexed' : 'indexed',
            'ocr_status' => 'none',
        ]);
        $doc->recomputeDisposal($project);
        $doc->saveQuietly();
        Audit::log('document.uploaded', $doc, "Uploaded {$doc->original_name} to {$project->name}", [], $project->client_id);

        if ($project->ocr_enabled) {
            ProcessDocumentOcr::dispatchAfterResponse($doc->id);
        }

        return response()->json(['ok' => true, 'message' => 'Uploaded.', 'id' => $doc->id]);
    }

    /* ---------------- Indexing and viewing ---------------- */

    public function queue(Project $project)
    {
        $this->allow($project, 'can_index');
        $next = Document::where('project_id', $project->id)->where('status', 'unindexed')->orderBy('id')->first();

        return $next ? redirect()->route('documents.show', $next) : redirect()->route('projects.show', $project)->with('success', 'Nothing left to index.');
    }

    public function show(Request $request, Document $document)
    {
        $project = $document->project()->with('department', 'fields')->first();
        $this->allow($project);
        Audit::log('document.viewed', $document, "Viewed {$document->original_name}", [], $document->client_id);

        $scope = Document::where('project_id', $project->id);
        $unindexed = $document->status === 'unindexed';
        $prevQ = (clone $scope)->where('id', '<', $document->id)->orderByDesc('id');
        $nextQ = (clone $scope)->where('id', '>', $document->id)->orderBy('id');
        if ($unindexed) {
            $prevQ->where('status', 'unindexed');
            $nextQ->where('status', 'unindexed');
        }
        $prev = $prevQ->first(['id']);
        $next = $nextQ->first(['id']);
        $remaining = (clone $scope)->where('status', 'unindexed')->where('id', '!=', $document->id)->count();
        $canIndex = $request->user()->canOn($project, 'can_index');
        $canDownload = $request->user()->canOn($project, 'can_download');
        $canDelete = $request->user()->canOn($project, 'can_delete');

        // Workflow, sharing, signature, form and hold panels
        $items = WorkflowItem::where('document_id', $document->id)->with(['workflow', 'events.user'])->latest('id')->get();
        $busy = $items->where('status', 'active')->pluck('workflow_id');
        $startable = $canIndex ? Workflow::where('project_id', $project->id)->where('active', true)->whereNotIn('id', $busy)->orderBy('name')->get()->filter(fn ($w) => $w->startNode()) : collect();
        $shares = $canDownload ? ShareLink::where('document_id', $document->id)->with('creator')->latest()->limit(10)->get() : collect();
        $signatures = $canDownload ? SignatureRequest::where('document_id', $document->id)->orderBy('seq')->orderBy('id')->limit(30)->get() : collect();
        $signRoles = \App\Models\Annotation::where('document_id', $document->id)->where('kind', 'sign')->whereNull('signature_request_id')->whereNull('applied_at')->selectRaw('text, count(*) n')->groupBy('text')->pluck('n', 'text');
        $team = $canDownload ? \App\Models\User::where('client_id', $document->client_id)->orderBy('name')->get(['id', 'name', 'email']) : collect();
        [$timeline, $openers] = $this->timeline($document, $items);
        $docTypes = DocType::orderBy('name')->pluck('name');
        $forms = Form::where('active', true)->orderBy('name')->get();
        $submissions = FormSubmission::where('document_id', $document->id)->with(['form', 'user'])->latest()->limit(20)->get();
        $versions = DocumentVersion::where('document_id', $document->id)->with('uploader')->orderByDesc('version_no')->get();
        $related = $document->linked()->where('id', '!=', $document->id)->filter(fn ($d) => $request->user()->canOn($d->project, 'can_view'));

        return view('documents.show', compact('document', 'project', 'prev', 'next', 'remaining', 'canIndex', 'canDownload', 'canDelete', 'items', 'startable', 'shares', 'signatures', 'docTypes', 'forms', 'submissions', 'related', 'versions', 'signRoles', 'team', 'timeline', 'openers'));
    }

    /** Everything that has happened to a document, newest first, plus who has opened it. */
    private function timeline(Document $document, $items): array
    {
        $logs = \App\Models\AuditLog::withoutGlobalScopes()->with('user')->where('client_id', $document->client_id)->where('subject_type', 'Document')->where('subject_id', $document->id)->latest()->limit(200)->get();
        $icons = ['signature.sent' => ['send', 'text-blue-600'], 'signature.viewed' => ['eye', 'text-slate-500'], 'signature.signed' => ['pen-line', 'text-emerald-600'], 'signature.declined' => ['circle-x', 'text-red-600'],
            'signature.reminded' => ['bell', 'text-amber-600'], 'signature.completed' => ['circle-check', 'text-emerald-600'], 'signature.cancelled' => ['x', 'text-slate-500'], 'signature.bulk' => ['users', 'text-blue-600'],
            'document.viewed' => ['eye', 'text-slate-400'], 'document.version' => ['history', 'text-purple-600'], 'document.uploaded' => ['upload', 'text-blue-600'], 'document.redacted' => ['eraser', 'text-red-600'], 'document.annotated' => ['highlighter', 'text-amber-600']];
        $rows = $logs->map(fn ($l) => ['at' => $l->created_at, 'icon' => $icons[$l->action][0] ?? 'circle', 'tone' => $icons[$l->action][1] ?? 'text-slate-500', 'text' => $l->description ?: $l->action, 'who' => $l->user?->name, 'view' => $l->action === 'document.viewed']);
        foreach ($items as $item) {
            foreach ($item->events as $e) {
                $rows->push(['at' => $e->created_at, 'icon' => 'git-branch', 'tone' => 'text-indigo-600', 'text' => ucfirst(str_replace('_', ' ', $e->action)).' · '.$item->workflow?->name.($e->node_name ? ' › '.$e->node_name : '').($e->outcome ? ' ('.$e->outcome.')' : '').($e->comment ? ': '.$e->comment : ''), 'who' => $e->user?->name, 'view' => false]);
            }
        }
        $openers = $logs->where('action', 'document.viewed')->groupBy('user_id')->map(fn ($g) => ['name' => $g->first()->user?->name ?? 'Someone', 'times' => $g->count(), 'last' => $g->first()->created_at])->sortByDesc('last')->values();

        return [$rows->sortByDesc('at')->values(), $openers];
    }

    public function update(Request $request, Document $document)
    {
        $project = $document->project()->with('fields')->first();
        $this->allow($project, 'can_index');
        $user = $request->user();

        $editable = $project->fields->filter(fn ($f) => $f->type !== 'calculated' && (! $f->read_only || $user->isAdmin()));
        $rules = [];
        foreach ($editable as $f) {
            $r = [$f->required ? 'required' : 'nullable'];
            $r = array_merge($r, match ($f->type) {
                'number' => ['integer'],
                'amount' => ['numeric'],
                'date' => ['date'],
                'yesno' => [Rule::in(['Yes', 'No'])],
                'picklist' => [Rule::in($f->options ?? [])],
                default => ['string', 'max:500'],
            });
            $rules["values.{$f->key}"] = $r;
        }
        $data = $request->validate($rules, [], $editable->mapWithKeys(fn ($f) => ["values.{$f->key}" => $f->label])->all());

        $before = $document->index_values ?? [];
        $values = $before;
        foreach ($editable as $f) {
            $v = $data['values'][$f->key] ?? null;
            $values[$f->key] = ($v === null || $v === '') ? null : ($f->type === 'date' ? date('Y-m-d', strtotime($v)) : (string) $v);
        }
        $document->index_values = $values;
        $document->applyCalculated($project);
        $values = $document->index_values;
        $document->applyNaming($project);
        $document->refreshSearchText();
        $document->status = 'indexed';
        $document->indexed_by = $user->id;
        $document->indexed_at = now();
        if (! $document->legal_hold) {
            $document->recomputeDisposal($project);
        }
        $document->save();

        Audit::log('document.indexed', $document, "Indexed {$document->original_name}", ['before' => $before, 'after' => $values], $document->client_id);

        // Workflows set to start automatically when a document becomes indexed
        foreach (Workflow::where('project_id', $project->id)->where('active', true)->where('auto_start', true)->get() as $wf) {
            app(WorkflowEngine::class)->start($document, $wf, $user);
        }

        if ($request->input('then') === 'next') {
            $next = Document::where('project_id', $project->id)->where('status', 'unindexed')->orderBy('id')->first();
            if ($next) {
                return $this->ok('Saved. Next document.', route('documents.show', $next));
            }

            return $this->ok('Saved. Nothing left to index.', route('projects.show', $project));
        }

        return $this->ok('Index values saved.');
    }

    /* ---------------- Versions ---------------- */

    public function storeVersion(Request $request, Document $document)
    {
        $project = $document->project;
        $this->allow($project, 'can_upload');
        abort_if($document->legal_hold, 403, 'This document is on legal hold and cannot be replaced.');
        $request->validate(['file' => ['required', 'file', 'max:102400', 'extensions:'.implode(',', self::EXTENSIONS)], 'note' => 'nullable|string|max:300'],
            ['file.extensions' => 'This file type is not allowed.', 'file.max' => 'Files can be up to 100 MB.']);

        $file = $request->file('file');
        if ($held = app(VirusScanner::class)->check($file, $document->client_id, $project->id, $request->user()->id, 'version')) {
            return $this->fail("{$held->original_name} failed the virus scan ({$held->threat}) and was held in quarantine. The document was not changed.");
        }
        $path = $this->disk()->putFileAs("clients/{$document->client_id}/projects/{$project->id}", $file, Str::uuid().'.'.strtolower($file->getClientOriginalExtension()));
        $next = (DocumentVersion::where('document_id', $document->id)->max('version_no') ?? 0) + 1;
        DocumentVersion::create([
            'client_id' => $document->client_id, 'document_id' => $document->id, 'version_no' => $next, 'path' => $document->path,
            'mime' => $document->mime, 'size' => $document->size, 'uploaded_by' => $document->uploaded_by, 'note' => $request->input('note') ?: null, 'replaced_at' => now(),
        ]);
        $document->update(['path' => $path, 'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id, 'ocr_status' => 'none', 'ocr_text' => null]);
        $project->ocr_enabled && ProcessDocumentOcr::dispatchAfterResponse($document->id);
        Audit::log('document.version', $document, "New version of {$document->original_name} uploaded (previous kept as version {$next})", [], $document->client_id);

        return $this->ok("New version uploaded. The previous file is kept as version {$next}.");
    }

    public function downloadVersion(DocumentVersion $version)
    {
        $document = $version->document;
        $this->allow($document->project, 'can_download');
        abort_unless($this->disk()->exists($version->path), 404, 'The stored file could not be found.');
        Audit::log('document.version_downloaded', $document, "Downloaded version {$version->version_no} of {$document->original_name}", [], $document->client_id);

        return $this->disk()->response($version->path, $document->original_name, ['Content-Type' => $version->mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'], 'attachment');
    }

    /** Swap: the chosen old file becomes current and the current file takes its place in the history. */
    public function restoreVersion(DocumentVersion $version)
    {
        $document = $version->document;
        $this->allow($document->project, 'can_upload');
        abort_if($document->legal_hold, 403, 'This document is on legal hold and cannot be replaced.');
        $cur = ['path' => $document->path, 'mime' => $document->mime, 'size' => $document->size];
        $document->update(['path' => $version->path, 'mime' => $version->mime, 'size' => $version->size, 'ocr_status' => 'none', 'ocr_text' => null]);
        $version->update($cur + ['replaced_at' => now(), 'note' => "Replaced when version {$version->version_no} was restored"]);
        $document->project->ocr_enabled && ProcessDocumentOcr::dispatchAfterResponse($document->id);
        Audit::log('document.version_restored', $document, "Restored version {$version->version_no} of {$document->original_name}", [], $document->client_id);

        return $this->ok("Version {$version->version_no} is now the current file. The file it replaced stays in the history.");
    }

    public function file(Document $document)
    {
        $project = $document->project;
        $this->allow($project);

        return $this->stream($document, 'inline');
    }

    public function download(Document $document)
    {
        $project = $document->project;
        $this->allow($project, 'can_download');
        Audit::log('document.downloaded', $document, "Downloaded {$document->original_name}", [], $document->client_id);

        return $this->stream($document, 'attachment');
    }

    private function stream(Document $document, string $disposition)
    {
        abort_unless($this->disk()->exists($document->path), 404, 'The stored file could not be found.');

        return $this->disk()->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }

    /* ---------------- Delete and recycle bin ---------------- */

    public function destroy(Request $request, Document $document)
    {
        $project = $document->project;
        $this->allow($project, 'can_delete');
        if ($document->legal_hold) {
            return $this->fail('This document is on legal hold and cannot be deleted.', 403);
        }
        $document->forceFill(['deleted_by' => $request->user()->id])->save();
        $document->delete();
        Audit::log('document.deleted', $document, "Moved {$document->original_name} to the recycle bin", [], $document->client_id);

        return $this->ok('Moved to the recycle bin.', route('projects.show', $project));
    }

    public function recycle(Request $request)
    {
        $ids = $request->user()->projectsWith('can_delete')->pluck('id');
        $documents = Document::onlyTrashed()->whereIn('project_id', $ids)->with('project.department', 'deleter')->latest('deleted_at')->paginate(25);

        return view('documents.recycle', compact('documents'));
    }

    public function restore(Request $request, int $id)
    {
        $doc = Document::onlyTrashed()->findOrFail($id);
        $this->allow($doc->project, 'can_delete');
        $doc->restore();
        $doc->forceFill(['deleted_by' => null])->save();
        Audit::log('document.restored', $doc, "Restored {$doc->original_name}", [], $doc->client_id);

        return $this->ok('Document restored.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\DocType;
use App\Models\Document;
use App\Models\Project;
use App\Models\Workflow;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Document types (Client Admin) and the email address each one receives mail at. */
class DocTypeController extends Controller
{
    use Responds;

    public function index()
    {
        $types = DocType::with('project', 'workflow')->orderBy('name')->get();
        $projects = Project::with('fields')->orderBy('name')->get();
        $workflows = Workflow::orderBy('name')->get();
        $inboundDomain = config('services.inbound.domain');

        return view('doctypes.index', compact('types', 'projects', 'workflows', 'inboundDomain'));
    }

    public function store(Request $request)
    {
        $type = DocType::create($this->data($request) + ['client_id' => $request->user()->client_id]);
        Audit::log('doctype.created', $type, "Document type {$type->name} created");

        return $this->ok('Document type added.');
    }

    public function update(Request $request, DocType $doctype)
    {
        $doctype->update($this->data($request, $doctype));
        Audit::log('doctype.updated', $doctype, "Document type {$doctype->name} updated");

        return $this->ok('Document type saved.');
    }

    public function destroy(DocType $doctype)
    {
        $doctype->delete();
        Audit::log('doctype.deleted', $doctype, "Document type {$doctype->name} deleted");

        return $this->ok('Document type deleted.');
    }

    /** Set the type of one document (from its page). */
    public function assign(Request $request, Document $document)
    {
        abort_unless($request->user()->canOn($document->project, 'can_index'), 403);
        $name = $request->validate(['doc_type' => 'nullable|string|max:100'])['doc_type'] ?? null;
        abort_if($name && ! DocType::where('name', $name)->exists(), 422, 'Unknown document type.');
        $document->update(['doc_type' => $name ?: null]);
        Audit::log('document.type', $document, "Document type of {$document->original_name}: ".($name ?: 'none'), [], $document->client_id);

        return $this->ok('Document type saved.');
    }

    private function data(Request $request, ?DocType $current = null): array
    {
        $clientId = $request->user()->client_id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('doc_types', 'name')->where('client_id', $clientId)->ignore($current?->id)],
            'email_alias' => ['nullable', 'regex:/^[a-z0-9][a-z0-9._-]{1,60}$/', Rule::unique('doc_types', 'email_alias')->ignore($current?->id)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('client_id', $clientId)->whereNull('deleted_at')],
            'workflow_id' => ['nullable', Rule::exists('workflows', 'id')->where('client_id', $clientId)->whereNull('deleted_at')],
            'index_field' => 'nullable|string|max:80',
            'subject_pattern' => ['nullable', 'string', 'max:200', function ($attr, $v, $fail) {
                if ($v && (@preg_match($v, '') === false || substr_count($v, '(') < 1)) {
                    $fail('The pattern must be a valid regular expression with one (group), for example /(CN-\d+)/i');
                }
            }],
        ], ['email_alias.regex' => 'Use lower-case letters, numbers, dots, dashes or underscores.', 'email_alias.unique' => 'That email address is already used.']);

        return array_map(fn ($v) => $v === '' ? null : $v, $data);
    }
}

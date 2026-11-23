<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Document;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** eForms: build forms (Client Admin) and fill them in on a document. */
class FormController extends Controller
{
    use Responds;

    public function index()
    {
        $forms = Form::withCount('submissions')->orderBy('name')->get();

        return view('forms.index', compact('forms'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        $form = Form::create($data + ['client_id' => $request->user()->client_id, 'fields' => []]);
        Audit::log('form.created', $form, "Form {$form->name} created");

        return $this->ok('Form created. Add its fields.', route('forms.edit', $form));
    }

    public function edit(Form $form)
    {
        $payload = ['saveUrl' => route('forms.save', $form), 'fields' => $form->fields ?? [], 'types' => Form::TYPES];

        return view('forms.edit', compact('form', 'payload'));
    }

    public function save(Request $request, Form $form)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'description' => 'nullable|string|max:300', 'fields' => 'nullable|array|max:40',
        ]);
        $fields = [];
        foreach ($data['fields'] ?? [] as $f) {
            $label = Str::limit(trim((string) ($f['label'] ?? '')), 100, '');
            $type = array_key_exists($f['type'] ?? '', Form::TYPES) ? $f['type'] : 'text';
            if ($label === '') {
                continue;
            }
            $key = Str::slug($label, '_') ?: 'field';
            for ($n = 2; collect($fields)->contains('key', $key); $n++) {
                $key = Str::slug($label, '_').'_'.$n;
            }
            $fields[] = [
                'key' => $key, 'label' => $label, 'type' => $type, 'required' => (bool) ($f['required'] ?? false),
                'options' => $type === 'select' ? array_values(array_filter(array_map(fn ($o) => Str::limit(trim((string) $o), 80, ''), (array) ($f['options'] ?? [])))) : [],
            ];
        }
        $form->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'fields' => $fields, 'active' => $request->boolean('active', true)]);
        Audit::log('form.updated', $form, "Form {$form->name} saved");

        return response()->json(['ok' => true, 'message' => 'Form saved.', 'fields' => $fields]);
    }

    public function destroy(Form $form)
    {
        $form->delete();
        Audit::log('form.deleted', $form, "Form {$form->name} deleted");

        return $this->ok('Form deleted.', route('forms'));
    }

    /** Standalone fill-in on a document (the same fields are used inline in workflow tasks). */
    public function submit(Request $request, Document $document, Form $form)
    {
        abort_unless($request->user()->canOn($document->project, 'can_index') && $form->active, 403);
        $data = $request->validate($form->rulesFor("form.{$form->id}"), [], collect($form->fields)->mapWithKeys(fn ($f) => ["form.{$form->id}.{$f['key']}" => $f['label']])->all());
        FormSubmission::create(['client_id' => $document->client_id, 'form_id' => $form->id, 'document_id' => $document->id, 'user_id' => $request->user()->id, 'data' => $data['form'][$form->id] ?? []]);
        Audit::log('form.submitted', $document, "{$form->name} filled in for {$document->original_name}", [], $document->client_id);

        return $this->ok("{$form->name} saved.");
    }
}

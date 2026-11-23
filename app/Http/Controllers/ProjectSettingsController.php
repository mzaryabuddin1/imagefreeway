<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\ProjectRight;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Project settings, index fields and per-user access (Client Admin). */
class ProjectSettingsController extends Controller
{
    use Responds;

    public function edit(Project $project)
    {
        $project->load('department', 'fields');
        $hidden = ProjectField::onlyTrashed()->where('project_id', $project->id)->orderBy('label')->get();
        $departments = Department::orderBy('name')->get();
        $users = User::where('client_id', $project->client_id)->where('role', 'user')->where('status', 'active')->orderBy('name')->get();
        $rights = ProjectRight::where('project_id', $project->id)->get()->keyBy('user_id');

        return view('structure.project', compact('project', 'hidden', 'departments', 'users', 'rights'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:200',
            'department_id' => ['required', Rule::exists('departments', 'id')->where('client_id', $project->client_id)->whereNull('deleted_at')],
        ]);
        $data['ocr_enabled'] = $request->boolean('ocr_enabled');
        $data['naming_pattern'] = trim((string) $request->validate(['naming_pattern' => 'nullable|string|max:200'])['naming_pattern']) ?: null;
        $before = $project->only(['name', 'department_id', 'ocr_enabled']);
        $project->update($data);
        Audit::log('project.updated', $project, "Project {$project->name} updated", ['before' => $before, 'after' => $project->only(array_keys($before))]);

        return $this->ok('Project saved.');
    }

    public function destroy(Project $project)
    {
        if ($project->documents()->withTrashed()->exists()) {
            return $this->fail('This project contains documents, so it cannot be deleted.');
        }
        $project->delete();
        Audit::log('project.deleted', $project, "Project {$project->name} deleted");

        return $this->ok('Project deleted.', route('structure'));
    }

    /* ---------------- Index fields ---------------- */

    public function storeField(Request $request, Project $project)
    {
        $data = $this->fieldData($request);
        $this->checkFormula($data, $project);
        $base = Str::slug($data['label'], '_') ?: 'field';
        $key = $base;
        for ($i = 2; ProjectField::withTrashed()->where('project_id', $project->id)->where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }
        $field = ProjectField::create($data + [
            'client_id' => $project->client_id, 'project_id' => $project->id, 'key' => $key,
            'sort' => ($project->fields()->max('sort') ?? 0) + 1,
        ]);
        Audit::log('field.created', $project, "Index field \"{$field->label}\" added to {$project->name}");

        return $this->ok('Index field added.');
    }

    public function updateField(Request $request, ProjectField $field)
    {
        $before = $field->only(['label', 'type', 'required', 'searchable', 'show_in_results', 'read_only', 'options']);
        $new = $this->fieldData($request);
        $this->checkFormula($new, Project::findOrFail($field->project_id), $field->key);
        $field->update($new);
        Audit::log('field.updated', $field->project_id ? Project::find($field->project_id) : null, "Index field \"{$field->label}\" updated", ['before' => $before, 'after' => $field->only(array_keys($before))]);

        return $this->ok('Index field saved.');
    }

    /** Hides a field but keeps every stored value; it can be restored (F-IDX-02). */
    public function destroyField(ProjectField $field)
    {
        $field->delete();
        Audit::log('field.hidden', Project::find($field->project_id), "Index field \"{$field->label}\" hidden");

        return $this->ok('Field hidden. Its stored values are kept and you can restore it.');
    }

    public function restoreField(int $field)
    {
        $f = ProjectField::onlyTrashed()->findOrFail($field);
        $f->restore();
        Audit::log('field.restored', Project::find($f->project_id), "Index field \"{$f->label}\" restored");

        return $this->ok('Field restored.');
    }

    public function moveField(Request $request, ProjectField $field)
    {
        $dir = $request->validate(['direction' => 'required|in:up,down'])['direction'];
        $fields = ProjectField::where('project_id', $field->project_id)->orderBy('sort')->orderBy('id')->get()->values();
        $i = $fields->search(fn ($f) => $f->id === $field->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if (isset($fields[$j])) {
            $order = $fields->pluck('id')->all();
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            foreach ($order as $pos => $id) {
                ProjectField::whereKey($id)->update(['sort' => $pos + 1]);
            }
        }

        return $this->ok('Field order updated.');
    }

    /** A formula may only use numeric fields of this project (and never itself). */
    private function checkFormula(array $data, Project $project, ?string $self = null): void
    {
        if ($data['type'] !== 'calculated') {
            return;
        }
        $ok = $project->fields()->whereIn('type', ['number', 'amount', 'calculated'])->pluck('key')->reject(fn ($k) => $k === $self)->all();
        $bad = array_diff(\App\Support\Formula::fields($data['formula']) ?? [], $ok);
        if ($bad) {
            $msg = 'Unknown or non-numeric field: '.implode(', ', $bad).'. Available: '.(implode(', ', $ok) ?: 'none yet');
            abort(response()->json(['message' => $msg, 'errors' => ['formula' => [$msg]]], 422));
        }
    }

    private function fieldData(Request $request): array
    {
        $data = $request->validate([
            'label' => 'required|string|max:80',
            'type' => ['required', Rule::in(array_keys(ProjectField::TYPES))],
            'options_text' => 'nullable|string|max:5000',
            'formula' => 'nullable|string|max:200',
        ]);
        if ($data['type'] === 'calculated') {
            $uses = \App\Support\Formula::fields((string) ($data['formula'] ?? ''));
            if ($uses === null || ! $uses) {
                abort(response()->json(['message' => 'Enter a formula using + - * / ( ), numbers and field names, e.g. quantity * unit_price', 'errors' => ['formula' => ['Enter a valid formula, e.g. quantity * unit_price']]], 422));
            }
        }
        $options = collect(preg_split('/\r\n|\r|\n|,/', (string) ($data['options_text'] ?? '')))->map('trim')->filter()->unique()->values()->all();
        if ($data['type'] === 'picklist' && ! $options) {
            abort(response()->json(['message' => 'Enter at least one permitted value for a pick-list.', 'errors' => ['options_text' => ['Enter at least one permitted value.']]], 422));
        }

        return [
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $data['type'] === 'picklist' ? $options : null,
            'formula' => $data['type'] === 'calculated' ? trim($data['formula']) : null,
            'required' => $request->boolean('required'),
            'searchable' => $request->boolean('searchable'),
            'show_in_results' => $request->boolean('show_in_results'),
            'read_only' => $request->boolean('read_only'),
        ];
    }

    /* ---------------- Access ---------------- */

    public function updateAccess(Request $request, Project $project)
    {
        $input = $request->input('rights', []);
        $users = User::where('client_id', $project->client_id)->where('role', 'user')->pluck('id');

        foreach ($users as $uid) {
            $row = [];
            foreach (array_keys(ProjectRight::RIGHTS) as $r) {
                $row[$r] = isset($input[$uid][$r]);
            }
            // Index/upload/download/delete only make sense together with View
            if (! $row['can_view']) {
                $row = array_map(fn () => false, $row);
            }
            if (in_array(true, $row, true)) {
                ProjectRight::updateOrCreate(['project_id' => $project->id, 'user_id' => $uid], $row);
            } else {
                ProjectRight::where('project_id', $project->id)->where('user_id', $uid)->delete();
            }
        }
        Audit::log('project.access', $project, "Access to {$project->name} updated");

        return $this->ok('Access saved.');
    }
}

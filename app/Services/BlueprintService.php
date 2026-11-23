<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Department;
use App\Models\DocType;
use App\Models\Form;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\Workflow;
use Illuminate\Support\Str;

/**
 * Captures a client's setup as plain data and builds the same setup in another client.
 * People, documents and access rights are never copied; email aliases are left empty because they are unique per platform.
 */
class BlueprintService
{
    public function capture(Client $client): array
    {
        $scope = fn ($m) => $m::withoutGlobalScopes()->where('client_id', $client->id);
        $projects = $scope(Project::class)->with(['fields' => fn ($q) => $q->orderBy('sort')])->orderBy('sort')->get();
        $projName = fn ($id) => $projects->firstWhere('id', $id)?->name;

        return [
            'modules' => $client->modules ?? [],
            'departments' => $scope(Department::class)->orderBy('sort')->get()->map(fn ($d) => [
                'name' => $d->name,
                'projects' => $projects->where('department_id', $d->id)->map(fn ($p) => [
                    'name' => $p->name, 'description' => $p->description, 'ocr_enabled' => (bool) $p->ocr_enabled,
                    'retention_years' => $p->retention_years, 'link_field' => $p->link_field, 'naming_pattern' => $p->naming_pattern,
                    'retention_field' => $p->fields->firstWhere('id', $p->retention_field_id)?->key,
                    'fields' => $p->fields->map(fn ($f) => $f->only(['label', 'key', 'type', 'required', 'searchable', 'show_in_results', 'read_only', 'options', 'formula']))->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
            'forms' => $scope(Form::class)->get()->map(fn ($f) => $f->only(['name', 'description', 'fields', 'active']))->values()->all(),
            'workflows' => $scope(Workflow::class)->get()->map(fn ($w) => [
                '_old_id' => $w->id, 'name' => $w->name, 'description' => $w->description, 'project' => $projName($w->project_id), 'graph' => $w->graph,
                'auto_start' => (bool) $w->auto_start, 'active' => (bool) $w->active,
            ])->values()->all(),
            'doc_types' => $scope(DocType::class)->get()->map(fn ($t) => [
                'name' => $t->name, 'project' => $projName($t->project_id), 'index_field' => $t->index_field, 'subject_pattern' => $t->subject_pattern,
                'workflow' => $t->workflow_id ? Workflow::withoutGlobalScopes()->find($t->workflow_id)?->name : null,
            ])->values()->all(),
        ];
    }

    /** What a blueprint contains, for the preview before anything is created. */
    public function summary(array $d): array
    {
        $projects = collect($d['departments'] ?? [])->flatMap(fn ($x) => $x['projects'] ?? []);

        return [
            'departments' => count($d['departments'] ?? []),
            'projects' => $projects->count(),
            'fields' => $projects->sum(fn ($p) => count($p['fields'] ?? [])),
            'workflows' => count($d['workflows'] ?? []),
            'forms' => count($d['forms'] ?? []),
            'doc_types' => count($d['doc_types'] ?? []),
            'modules' => collect($d['modules'] ?? [])->filter()->keys()->all(),
            'workflow_names' => collect($d['workflows'] ?? [])->pluck('name')->all(),
            'project_names' => $projects->pluck('name')->all(),
        ];
    }

    /** Builds everything in $client. Call inside a transaction: any failure must leave nothing behind. */
    public function apply(Client $client, array $d): void
    {
        $projectIds = [];
        foreach ($d['departments'] ?? [] as $i => $dept) {
            $department = Department::create(['client_id' => $client->id, 'name' => $dept['name'], 'sort' => $i]);
            foreach ($dept['projects'] ?? [] as $j => $p) {
                $project = Project::create([
                    'client_id' => $client->id, 'department_id' => $department->id, 'name' => $p['name'], 'description' => $p['description'] ?? null,
                    'ocr_enabled' => $p['ocr_enabled'] ?? true, 'retention_years' => $p['retention_years'] ?? null,
                    'link_field' => $p['link_field'] ?? null, 'naming_pattern' => $p['naming_pattern'] ?? null, 'sort' => $j,
                ]);
                $keyed = [];
                foreach ($p['fields'] ?? [] as $k => $f) {
                    $keyed[$f['key']] = ProjectField::create([
                        'client_id' => $client->id, 'project_id' => $project->id, 'sort' => $k, 'label' => $f['label'], 'key' => $f['key'], 'type' => $f['type'],
                        'required' => $f['required'] ?? false, 'searchable' => $f['searchable'] ?? false, 'show_in_results' => $f['show_in_results'] ?? false,
                        'read_only' => $f['read_only'] ?? false, 'options' => $f['options'] ?? null, 'formula' => $f['formula'] ?? null,
                    ]);
                }
                if (! empty($p['retention_field']) && isset($keyed[$p['retention_field']])) {
                    $project->update(['retention_field_id' => $keyed[$p['retention_field']]->id]);
                }
                $projectIds[$p['name']] = $project->id;
            }
        }

        foreach ($d['forms'] ?? [] as $f) {
            Form::create(['client_id' => $client->id, 'name' => $f['name'], 'description' => $f['description'] ?? null, 'fields' => $f['fields'] ?? [], 'active' => $f['active'] ?? true]);
        }

        $workflowIds = [];
        foreach ($d['workflows'] ?? [] as $w) {
            $workflowIds[$w['name']] = Workflow::create([
                'client_id' => $client->id, 'project_id' => $projectIds[$w['project'] ?? ''] ?? null, 'name' => $w['name'], 'description' => $w['description'] ?? null,
                'graph' => $this->clean($w['graph'] ?? ['nodes' => [], 'edges' => []]), 'auto_start' => $w['auto_start'] ?? false, 'active' => $w['active'] ?? true,
            ])->id;
        }
        // "Go to another workflow" steps point at workflows by id, so they are re-pointed by name
        $idToName = collect($d['workflows'] ?? [])->mapWithKeys(fn ($w) => [$w['_old_id'] ?? null => $w['name']]);
        foreach ($d['workflows'] ?? [] as $w) {
            $wf = Workflow::withoutGlobalScopes()->find($workflowIds[$w['name']]);
            $g = $wf->graph;
            foreach ($g['nodes'] as &$n) {
                if ($n['type'] === 'goto') {
                    $n['config']['workflow_id'] = $workflowIds[$idToName[$n['config']['workflow_id'] ?? 0] ?? ''] ?? 0;
                }
            }
            unset($n);
            $wf->update(['graph' => $g]);
        }

        foreach ($d['doc_types'] ?? [] as $t) {
            DocType::create([
                'client_id' => $client->id, 'name' => $t['name'], 'project_id' => $projectIds[$t['project'] ?? ''] ?? null,
                'workflow_id' => $workflowIds[$t['workflow'] ?? ''] ?? null, 'index_field' => $t['index_field'] ?? null, 'subject_pattern' => $t['subject_pattern'] ?? null,
            ]);
        }
    }

    /** People differ per client, so assignees and "escalate to a person" are emptied (Client Admins get the tasks until assigned). */
    private function clean(array $graph): array
    {
        $graph['nodes'] = array_map(function ($n) {
            if (($n['type'] ?? '') === 'task') {
                $n['config']['assignees'] = [];
                if (ctype_digit((string) ($n['config']['escalate_to'] ?? ''))) {
                    $n['config']['escalate_to'] = '';
                }
            }

            return $n;
        }, $graph['nodes'] ?? []);

        return $graph;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\DocType;
use App\Models\Form;
use App\Models\Project;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowItem;
use App\Support\Audit;
use App\Support\Flow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Visual workflow builder (Client Admin). */
class WorkflowAdminController extends Controller
{
    use Responds;

    public function index()
    {
        $workflows = Workflow::with('project.department')->withCount(['items as active_items' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get();
        $projects = Project::with('department')->orderBy('name')->get();

        return view('workflows.index', compact('workflows', 'projects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'project_id' => ['required', Rule::exists('projects', 'id')->where('client_id', $request->user()->client_id)->whereNull('deleted_at')],
        ]);
        $graph = ['nodes' => [
            ['id' => 'start', 'type' => 'start', 'name' => 'Start', 'x' => 80, 'y' => 160, 'config' => []],
            ['id' => 'review', 'type' => 'task', 'name' => 'Review', 'x' => 340, 'y' => 140, 'config' => ['options' => ['Approve', 'Reject']]],
            ['id' => 'done', 'type' => 'end', 'name' => 'Approved', 'x' => 640, 'y' => 100, 'config' => ['label' => 'Approved']],
            ['id' => 'rejected', 'type' => 'end', 'name' => 'Rejected', 'x' => 640, 'y' => 260, 'config' => ['label' => 'Rejected']],
        ], 'edges' => [
            ['from' => 'start', 'port' => 'Next', 'to' => 'review'],
            ['from' => 'review', 'port' => 'Approve', 'to' => 'done'],
            ['from' => 'review', 'port' => 'Reject', 'to' => 'rejected'],
        ]];
        $wf = Workflow::create($data + ['client_id' => $request->user()->client_id, 'graph' => Flow::sanitize($graph), 'auto_start' => $request->boolean('auto_start')]);
        Audit::log('workflow.created', $wf, "Workflow {$wf->name} created");

        return $this->ok('Workflow created. Drag steps around and connect them.', route('workflows.edit', $wf));
    }

    public function edit(Workflow $workflow)
    {
        $workflow->load('project.fields', 'project.department');
        $payload = [
            'saveUrl' => route('workflows.save', $workflow),
            'graph' => $workflow->graph ?? ['nodes' => [], 'edges' => []],
            'types' => collect(Flow::TYPES)->map(fn ($t, $k) => ['key' => $k, 'label' => $t[0], 'icon' => $t[1], 'color' => $t[2], 'help' => $t[3]])->values(),
            'operators' => Flow::OPERATORS,
            'users' => User::where('client_id', $workflow->client_id)->where('status', 'active')->whereIn('role', ['user', 'client_admin'])->orderBy('name')->get(['id', 'name']),
            'fields' => $workflow->project->fields->map(fn ($f) => ['key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'options' => $f->options])->values(),
            'docTypes' => DocType::orderBy('name')->pluck('name'),
            'forms' => Form::where('active', true)->orderBy('name')->get(['id', 'name']),
            'workflows' => Workflow::where('id', '!=', $workflow->id)->orderBy('name')->get(['id', 'name']),
            'busy' => WorkflowItem::where('workflow_id', $workflow->id)->where('status', 'active')->whereNotNull('node_id')->pluck('node_id')->unique()->values(),
        ];

        return view('workflows.edit', compact('workflow', 'payload'));
    }

    /** Saves name, switches and the whole graph in one go (JSON from the builder). */
    public function save(Request $request, Workflow $workflow)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:300',
            'link_field' => 'nullable|string|max:80',
            'graph' => 'required|array',
        ]);
        $graph = Flow::sanitize($data['graph']);

        // A step that still has documents waiting at it cannot disappear.
        $kept = collect($graph['nodes'])->pluck('id');
        $stuck = WorkflowItem::where('workflow_id', $workflow->id)->where('status', 'active')->whereNotNull('node_id')->get()->filter(fn ($i) => ! $kept->contains($i->node_id));
        if ($stuck->isNotEmpty()) {
            $names = collect($workflow->graph['nodes'] ?? [])->whereIn('id', $stuck->pluck('node_id'))->pluck('name')->implode(', ');

            return $this->fail("Documents are still waiting at: {$names}. Finish them first, or keep that step.");
        }

        $keys = $workflow->project->fields()->pluck('key')->all();
        $link = in_array($data['link_field'] ?? '', $keys, true) ? $data['link_field'] : null;
        $workflow->update([
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'graph' => $graph,
            'auto_start' => $request->boolean('auto_start'), 'active' => $request->boolean('active', true),
        ]);
        $workflow->project->update(['link_field' => $link]);
        Audit::log('workflow.updated', $workflow, "Workflow {$workflow->name} saved (".count($graph['nodes']).' steps)');

        return response()->json(['ok' => true, 'message' => 'Workflow saved.', 'graph' => $graph, 'issues' => Flow::issues($graph)]);
    }

    public function destroy(Workflow $workflow)
    {
        if (WorkflowItem::where('workflow_id', $workflow->id)->where('status', 'active')->exists()) {
            return $this->fail('This workflow still has documents in progress. Switch it off instead, or finish them first.');
        }
        $workflow->delete();
        Audit::log('workflow.deleted', $workflow, "Workflow {$workflow->name} deleted");

        return $this->ok('Workflow deleted.', route('workflows'));
    }
}

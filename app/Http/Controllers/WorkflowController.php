<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Document;
use App\Models\Workflow;
use App\Models\WorkflowItem;
use App\Services\WorkflowEngine;
use Illuminate\Http\Request;

/** Workflow inbox and actions for everyone in a client. */
class WorkflowController extends Controller
{
    use Responds;

    public function inbox(Request $request)
    {
        $user = $request->user();
        $mine = WorkflowItem::waitingFor($user);
        $all = $user->isClientAdmin()
            ? WorkflowItem::where('status', 'active')->with(['document.project', 'workflow'])->oldest('stage_entered_at')->get()
            : collect();

        return view('workflows.inbox', compact('mine', 'all'));
    }

    /** "Work-through" mode: open the first waiting item. */
    public function work(Request $request)
    {
        $first = WorkflowItem::waitingFor($request->user())->first();

        return $first
            ? redirect()->route('documents.show', $first->document)
            : redirect()->route('workflow')->with('success', 'Your inbox is empty.');
    }

    public function act(Request $request, WorkflowItem $item, WorkflowEngine $engine)
    {
        $data = $request->validate([
            'outcome' => 'required|string|max:60',
            'comment' => 'nullable|string|max:1000',
            'form' => 'nullable|array',
        ]);

        $engine->act($item, $request->user(), $data['outcome'], $data['comment'] ?? null, $data['form'] ?? []);
        $message = "Done: {$data['outcome']}.";

        if ($request->input('then') === 'next') {
            $next = WorkflowItem::waitingFor($request->user())->first();

            return $next
                ? $this->ok("$message Next item.", route('documents.show', $next->document))
                : $this->ok("$message Your inbox is empty.", route('workflow'));
        }

        return $this->ok($message);
    }

    public function start(Request $request, Document $document, WorkflowEngine $engine)
    {
        $project = $document->project;
        abort_unless($request->user()->canOn($project, 'can_index'), 403, 'You cannot start workflows in this project.');
        $data = $request->validate(['workflow_id' => 'required|integer']);
        $workflow = Workflow::where('project_id', $project->id)->where('active', true)->findOrFail($data['workflow_id']);

        if (! $document->index_values && $project->fields()->exists()) {
            return $this->fail('Index this document first, then start the workflow.');
        }

        return $engine->start($document, $workflow, $request->user())
            ? $this->ok('Workflow started.')
            : $this->fail('This document is already in that workflow.');
    }
}

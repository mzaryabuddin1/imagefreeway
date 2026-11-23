@extends('layouts.app')
@section('title', 'Workflows')

@section('content')
<div class="mb-6"><a href="{{ route('workflow') }}" class="text-sm text-brand hover:underline">← Workflow inbox</a>
    <h1 class="mt-1 text-2xl font-bold">Workflows</h1>
    <p class="text-sm text-slate-500">Draw the process: tasks with their own options, emails, if/else routing, duplicate checks, signatures. Open a workflow to edit it on the canvas.</p></div>

<form method="post" action="{{ route('workflows.store') }}" class="card card-pad mb-6 grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto_auto]">
    @csrf
    <div><label class="label">Workflow name</label><input name="name" class="input" placeholder="e.g. Invoice approval" required></div>
    <div><label class="label">Project</label>
        <select name="project_id" class="input" required>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->department->name }} › {{ $p->name }}</option>@endforeach</select></div>
    <label class="flex items-center gap-2 pb-2.5 text-sm"><input type="checkbox" name="auto_start" value="1" class="rounded border-slate-300"> Start automatically once indexed</label>
    <button class="btn btn-primary"><i data-lucide="plus"></i> Create</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">Workflow</th><th class="th">Project</th><th class="th">Steps</th><th class="th">In progress</th><th class="th">Status</th><th class="th"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($workflows as $w)
            <tr>
                <td class="td font-semibold">{{ $w->name }} @if($w->auto_start)<span class="badge badge-blue ml-1">Auto-start</span>@endif</td>
                <td class="td text-slate-600">{{ $w->project->department->name }} › {{ $w->project->name }}</td>
                <td class="td">{{ count($w->graph['nodes'] ?? []) }}</td>
                <td class="td">{{ $w->active_items }}</td>
                <td class="td"><span class="badge {{ $w->active ? 'badge-green' : 'badge-slate' }}">{{ $w->active ? 'On' : 'Off' }}</span></td>
                <td class="td text-right"><a href="{{ route('workflows.edit', $w) }}" class="btn btn-ghost btn-sm"><i data-lucide="pencil"></i> Open builder</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-slate-400">No workflows yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

@extends('layouts.app')
@section('title', $workflow->name)

@section('content')
<div class="mb-3 flex flex-wrap items-end gap-3">
    <div class="min-w-0">
        <a href="{{ route('workflows') }}" class="text-sm text-brand hover:underline">← Workflows</a>
        <p class="text-xs text-slate-500">{{ $workflow->project->department->name }} › {{ $workflow->project->name }}</p>
    </div>
    <div class="min-w-56 flex-1"><label class="label">Workflow name</label><input id="fb-name" class="input" value="{{ $workflow->name }}" maxlength="120"></div>
    <div><label class="label">Link documents by</label>
        <select id="fb-link" class="input"><option value="">Nothing</option>@foreach($workflow->project->fields as $f)<option value="{{ $f->key }}" @selected($workflow->project->link_field === $f->key)>{{ $f->label }}</option>@endforeach</select></div>
    <label class="flex items-center gap-2 pb-2.5 text-sm"><input id="fb-active" type="checkbox" class="rounded border-slate-300" @checked($workflow->active)> On</label>
    <label class="flex items-center gap-2 pb-2.5 text-sm"><input id="fb-auto" type="checkbox" class="rounded border-slate-300" @checked($workflow->auto_start)> Start once indexed</label>
    <div class="flex items-center gap-2 pb-0.5">
        <span id="fb-dirty" class="badge badge-amber hidden">Unsaved</span>
        <button type="button" id="fb-issues-btn" class="btn btn-ghost btn-sm">…</button>
        <button type="button" id="fb-save" class="btn btn-primary"><i data-lucide="save"></i> Save</button>
    </div>
</div>

<div id="fb" data-flow-builder data-payload="{{ json_encode($payload) }}" class="fb">
    <div id="fb-viewport" class="fb-viewport">
        <div id="fb-world" class="fb-world">
            <svg id="fb-edges" class="fb-svg"></svg>
            <div id="fb-nodes"></div>
            <button type="button" id="fb-edge-del" class="fb-edge-del hidden" data-ui title="Remove this connection">×</button>
        </div>
    </div>

    <div class="fb-palette" data-ui>
        <p>Add a step</p>
        @foreach(\App\Support\Flow::TYPES as $key => [$label, $icon, $color])
            @continue($key === 'start')
            <button type="button" data-add="{{ $key }}" title="{{ \App\Support\Flow::TYPES[$key][3] }}"><i data-lucide="{{ $icon }}" style="color: {{ $color }}"></i>{{ $label }}</button>
        @endforeach
    </div>

    <div class="fb-tools" data-ui>
        <button type="button" data-zoom="in" title="Zoom in"><i data-lucide="zoom-in"></i></button>
        <button type="button" data-zoom="out" title="Zoom out"><i data-lucide="zoom-out"></i></button>
        <button type="button" data-fit title="Fit to screen"><i data-lucide="maximize"></i></button>
        <button type="button" data-tidy title="Tidy up the layout"><i data-lucide="layout-grid"></i></button>
    </div>

    <div class="fb-issues hidden" data-ui><p>Things to check</p><ul id="fb-issues"></ul></div>
    <div id="fb-inspector" class="fb-inspector" data-ui></div>
    <div id="fb-quick" class="fb-quick hidden" data-ui></div>
    <p class="fb-help" data-ui>Drag the canvas to move · Ctrl + scroll to zoom · Click a connection, then Delete, to remove it</p>
</div>

<form method="post" action="{{ route('workflows.destroy', $workflow) }}" data-danger data-confirm-title="Delete {{ $workflow->name }}?" data-confirm="Workflows with documents in progress cannot be deleted." data-confirm-button="Yes, delete" class="mt-4 flex justify-end">
    @csrf @method('DELETE')<button class="btn btn-ghost text-red-600"><i data-lucide="trash-2"></i> Delete workflow</button></form>
@endsection

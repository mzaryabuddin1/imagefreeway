@extends('layouts.app')
@section('title', 'Reports')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Reports</h1><p class="text-sm text-slate-500">Counts and totals from your index values and workflow state.</p></div>

@if(! $project)
    <div class="card card-pad text-center text-sm text-slate-500">You do not have access to any project yet.</div>
@else
<form method="get" action="{{ route('reports') }}" data-no-confirm class="card card-pad mb-6 grid items-end gap-4 sm:grid-cols-3 lg:grid-cols-6">
    <div class="lg:col-span-2"><label class="label">Project</label><select name="project" class="input">@foreach($projects as $p)<option value="{{ $p->id }}" @selected($p->id === $project->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div><label class="label">Group by</label><select name="group" class="input"><option value="status" @selected($group==='status')>Status</option><option value="month" @selected($group==='month')>Month added</option>@foreach($fields->whereIn('type', ['picklist','yesno','text']) as $f)<option value="{{ $f->key }}" @selected($group===$f->key)>{{ $f->label }}</option>@endforeach</select></div>
    <div><label class="label">Add up</label><select name="sum" class="input"><option value="">—</option>@foreach($fields->whereIn('type', ['number','amount','calculated']) as $f)<option value="{{ $f->key }}" @selected($sum===$f->key)>{{ $f->label }}</option>@endforeach</select></div>
    <div><label class="label">From</label><input type="date" name="from" value="{{ request('from') }}" class="input"></div>
    <div><label class="label">To</label><input type="date" name="to" value="{{ request('to') }}" class="input"></div>
    <div class="flex gap-2 sm:col-span-3 lg:col-span-6"><button class="btn btn-primary">Run report</button>
        <a data-no-ajax href="{{ route('reports', array_merge(request()->query(), ['project' => $project->id, 'export' => 'csv'])) }}" class="btn btn-ghost"><i data-lucide="download"></i> Export CSV</a></div>
</form>

@php($max = max(1, (int) $rows->max('total')))
<section class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Documents by {{ $groupLabel }}</div>
    <div class="space-y-3 p-5">
        @forelse($rows as $r)
            <div>
                <div class="mb-1 flex justify-between text-sm"><span class="font-medium">{{ $r->label }}</span>
                    <span class="text-slate-500">{{ $r->total }} {{ str('document')->plural($r->total) }}@if($sumLabel) · {{ $sumLabel }} {{ number_format((float) $r->sum_value, 2) }}@endif</span></div>
                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand" style="width: {{ round($r->total / $max * 100) }}%"></div></div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-slate-400">No documents match.</p>
        @endforelse
    </div>
</section>

<section class="card">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Workflow state</div>
    <ul class="divide-y divide-slate-100">
        @forelse($workflowRows as $label => $n)
            <li class="flex justify-between px-5 py-2.5 text-sm"><span>{{ $label }}</span><span class="font-medium">{{ $n }}</span></li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-slate-400">No workflow activity for this project.</li>
        @endforelse
    </ul>
</section>

@if(auth()->user()->isClientAdmin())
<section class="card mt-6">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Scheduled reports <span class="ml-2 text-xs font-normal text-slate-500">emailed with your branding, CSV attached</span></div>
    @foreach($schedules as $s)
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm">
            <div class="min-w-0 flex-1">
                <div class="font-medium">{{ $s->name }} @unless($s->active)<span class="badge badge-slate">Paused</span>@endunless</div>
                <div class="text-xs text-slate-500">{{ $s->project?->name }} · {{ \App\Models\ScheduledReport::FREQUENCIES[$s->frequency] }} · {{ $s->recipients }} · last sent {{ $s->last_sent_at?->diffForHumans() ?? 'never' }}</div>
            </div>
            <form method="post" action="{{ route('schedules.send', $s) }}" data-confirm-title="Send it now?" data-confirm="The report is emailed to {{ $s->recipients }}." data-confirm-button="Yes, send">@csrf<button class="btn btn-ghost btn-sm">Send now</button></form>
            <form method="post" action="{{ route('schedules.toggle', $s) }}" data-no-confirm>@csrf<button class="btn btn-ghost btn-sm">{{ $s->active ? 'Pause' : 'Resume' }}</button></form>
            <form method="post" action="{{ route('schedules.destroy', $s) }}" data-danger data-confirm-title="Delete this scheduled report?" data-confirm="It will stop being sent." data-confirm-button="Yes, delete">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600">Delete</button></form>
        </div>
    @endforeach
    @if($project)
    <form method="post" action="{{ route('schedules.store') }}" class="grid items-end gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
        @csrf
        <input type="hidden" name="project_id" value="{{ $project->id }}">
        <div><label class="label">Name <span class="text-red-500">*</span></label><input name="name" class="input" required maxlength="120" placeholder="Weekly invoices"></div>
        <div><label class="label">How often <span class="text-red-500">*</span></label><select name="frequency" class="input">@foreach(\App\Models\ScheduledReport::FREQUENCIES as $k => $l)<option value="{{ $k }}" @selected($k==='weekly')>{{ $l }}</option>@endforeach</select></div>
        <div><label class="label">Project</label><input class="input bg-slate-50" value="{{ $project->name }}" disabled></div>
        <div><label class="label">Group by</label><select name="group_by" class="input"><option value="status">Status</option><option value="month">Month added</option>@foreach($fields->whereIn('type', ['picklist','yesno','text']) as $f)<option value="{{ $f->key }}">{{ $f->label }}</option>@endforeach</select></div>
        <div><label class="label">Add up</label><select name="sum_by" class="input"><option value="">—</option>@foreach($fields->whereIn('type', ['number','amount','calculated']) as $f)<option value="{{ $f->key }}">{{ $f->label }}</option>@endforeach</select></div>
        <div><label class="label">Send to <span class="text-red-500">*</span></label><input name="recipients" class="input" required placeholder="a@x.com, b@x.com"></div>
        <div class="sm:col-span-2 lg:col-span-3"><button class="btn btn-primary">Schedule report</button></div>
    </form>
    @endif
</section>
@endif
@endif
@endsection

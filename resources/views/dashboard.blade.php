@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">Hello, {{ auth()->user()->name }}</h1>
    <p class="text-sm text-slate-500">Here is what is happening in {{ auth()->user()->isSuperAdmin() ? 'the platform' : 'your workspace' }}.</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['clients', 'Active clients', 'building-2', $stats['clients'] ?? null],
        ['suspended', 'Suspended clients', 'pause', $stats['suspended'] ?? null],
        ['documents', 'Documents', 'file-text', $stats['documents'] ?? null],
        ['unindexed', 'Waiting to be indexed', 'pencil', $stats['unindexed'] ?? null],
        ['waiting', 'In your workflow inbox', 'git-branch', $stats['waiting'] ?? null],
        ['overdue', 'Overdue workflow items', 'clock', $stats['overdue'] ?? null],
        ['storage', 'Storage used (MB)', 'layers', $stats['storage'] ?? null],
        ['users', 'Active users', 'users', $stats['users'] ?? null],
        ['modules', 'Add-on modules on', 'sparkles', $stats['modules'] ?? null],
    ] as [$key, $label, $icon, $value])
        @continue($value === null)
        <div class="card card-pad flex items-center gap-4">
            <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl {{ $key === 'overdue' && $value > 0 ? 'bg-red-50 text-red-600' : 'bg-brand-soft text-brand' }}"><i data-lucide="{{ $icon }}"></i></span>
            <div><p class="text-2xl font-bold leading-none">{{ is_numeric($value) ? number_format($value, $key === 'storage' ? 1 : 0) : $value }}</p><p class="mt-1 text-sm text-slate-500">{{ $label }}</p></div>
        </div>
    @endforeach
</div>

@if(! empty($charts))
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Documents added · last 30 days</h2><p class="text-xs text-slate-500">Hover a bar to see the day's count.</p></div>
        <div class="p-5">@include('charts.chart', ['type' => 'area', 'data' => $charts['per_day'], 'name' => 'Documents'])</div>
    </section>

    @if(auth()->user()->isSuperAdmin())
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Clients by status</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'donut', 'data' => $charts['client_status'], 'center' => 'Clients'])</div>
        </section>
        <section class="card lg:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Documents per client</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'bar', 'data' => $charts['docs_per_client']])</div>
        </section>
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Users per client</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'bar', 'data' => $charts['users_per_client']])</div>
        </section>
    @else
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Indexing progress</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'donut', 'data' => $charts['status'], 'center' => 'Documents'])</div>
        </section>
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Documents by project</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'bar', 'data' => $charts['by_project']])</div>
        </section>
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Documents by type</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'donut', 'data' => $charts['by_type'], 'center' => 'Documents'])</div>
        </section>
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Where work is waiting</h2><p class="text-xs text-slate-500">Workflow items by current step.</p></div>
            <div class="p-5">@include('charts.chart', ['type' => 'bar', 'data' => $charts['where']])</div>
        </section>
        <section class="card">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">How finished work ended</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'donut', 'data' => $charts['outcomes'], 'center' => 'Finished'])</div>
        </section>
        @if($charts['amount_label'])
        <section class="card lg:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">{{ $charts['amount_label'] }} · by month added</h2></div>
            <div class="p-5">@include('charts.chart', ['type' => 'column', 'data' => $charts['amounts'], 'prefix' => '$', 'name' => 'Amount'])</div>
        </section>
        @endif
    @endif
</div>
@endif

@if(auth()->user()->client_id)
<div class="card mt-6">
    <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Unindexed documents</h2><p class="text-xs text-slate-500">Waiting for index values. Click a project to start indexing.</p></div>
    <ul class="divide-y divide-slate-100">
        @forelse($queues as $q)
            <li><a href="{{ route('projects.queue', $q->project) }}" class="flex items-center justify-between px-5 py-3 text-sm hover:bg-slate-50">
                <span><span class="font-medium">{{ $q->project->name }}</span> <span class="text-slate-400">· {{ $q->project->department->name }}</span></span>
                <span class="badge badge-amber">{{ $q->pending }} waiting</span></a></li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-slate-400">Nothing is waiting to be indexed.</li>
        @endforelse
    </ul>
</div>
@endif

@if(auth()->user()->isAdmin())
<div class="card mt-6">
    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
        <h2 class="font-semibold">Recent activity</h2>
        <a href="{{ route('audit') }}" class="text-sm font-medium text-brand hover:underline">View audit log</a>
    </div>
    <ul class="divide-y divide-slate-100">
        @forelse($recent as $log)
            <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                <span><span class="font-medium">{{ $log->user?->name ?? 'System' }}</span> <span class="text-slate-500">· {{ $log->description ?? $log->action }}</span></span>
                <span class="shrink-0 text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</span>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-slate-400">Nothing yet.</li>
        @endforelse
    </ul>
</div>
@endif
@endsection

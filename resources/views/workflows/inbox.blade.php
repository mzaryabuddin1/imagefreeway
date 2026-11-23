@extends('layouts.app')
@section('title', 'Workflow')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Workflow inbox</h1><p class="text-sm text-slate-500">Documents waiting for you. Answering one opens the next.</p></div>
    <div class="flex gap-2">
        @if($mine->isNotEmpty())<a href="{{ route('workflow.work') }}" class="btn btn-primary"><i data-lucide="play"></i> Start working ({{ $mine->count() }})</a>@endif
        @if(auth()->user()->isClientAdmin())<a href="{{ route('workflows') }}" class="btn btn-ghost"><i data-lucide="settings"></i> Manage workflows</a>@endif
    </div>
</div>

<section class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Waiting for me</div>
    <ul class="divide-y divide-slate-100">
        @forelse($mine as $i)
            <li><a href="{{ route('documents.show', $i->document) }}" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm hover:bg-slate-50">
                <span><span class="font-medium text-brand">{{ $i->document->original_name }}</span>
                    <span class="text-slate-400">· {{ $i->workflow->name }} › {{ $i->nodeName() }}</span></span>
                <span class="flex items-center gap-2 text-xs text-slate-500">@if($i->isOverdue())<span class="badge badge-amber">Overdue</span>@endif {{ $i->stage_entered_at?->diffForHumans() }}</span></a></li>
        @empty
            <li class="px-5 py-10 text-center text-sm text-slate-400">Nothing is waiting for you.</li>
        @endforelse
    </ul>
</section>

@if(auth()->user()->isClientAdmin())
<section class="card">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">All items in progress</div>
    <ul class="divide-y divide-slate-100">
        @forelse($all as $i)
            <li><a href="{{ route('documents.show', $i->document) }}" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm hover:bg-slate-50">
                <span><span class="font-medium">{{ $i->document->original_name }}</span>
                    <span class="text-slate-400">· {{ $i->workflow->name }} › {{ $i->nodeName() }}</span></span>
                <span class="flex items-center gap-2 text-xs text-slate-500">@if($i->isOverdue())<span class="badge badge-amber">Overdue</span>@endif {{ $i->stage_entered_at?->diffForHumans() }}</span></a></li>
        @empty
            <li class="px-5 py-10 text-center text-sm text-slate-400">No workflow items in progress.</li>
        @endforelse
    </ul>
</section>
@endif
@endsection

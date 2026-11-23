@extends('layouts.app')
@section('title', 'Documents')

@php
    $u = auth()->user();
    $folder = '<svg viewBox="0 0 80 64" class="h-16 w-20 drop-shadow-sm" aria-hidden="true"><path d="M4 10a6 6 0 0 1 6-6h18l8 8h34a6 6 0 0 1 6 6v36a6 6 0 0 1-6 6H10a6 6 0 0 1-6-6z" fill="#f59e0b"/><path d="M4 22a6 6 0 0 1 6-6h60a6 6 0 0 1 6 6v32a6 6 0 0 1-6 6H10a6 6 0 0 1-6-6z" fill="#fbbf24"/></svg>';
    $level = $current ? 'project' : ($dept ? 'dept' : 'root');
@endphp

@section('content')
<div class="mb-4"><h1 class="text-2xl font-bold">Documents</h1></div>

@if($depts->isEmpty())
    <div class="card card-pad text-center text-sm text-slate-500">
        You have no projects yet.
        @if($u->isClientAdmin()) <a href="{{ route('structure') }}" class="font-medium text-brand hover:underline">Create a department and project</a>.
        @else Ask your administrator to give you access to a project. @endif
    </div>
@else
<div class="card overflow-hidden">
    {{-- Path bar --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50/60 px-4 py-2.5">
        @if($level !== 'root')
            <a href="{{ $level === 'project' ? route('documents', ['dept' => $dept->id]) : route('documents') }}" class="icon-btn" title="Back"><i data-lucide="arrow-left"></i></a>
        @endif
        <nav class="mr-auto flex min-w-0 flex-wrap items-center gap-1 text-sm" aria-label="Path">
            <a href="{{ route('documents') }}" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 {{ $level === 'root' ? 'font-semibold text-slate-800' : 'text-slate-500 hover:bg-slate-100' }}"><i data-lucide="folder-open" class="text-amber-500"></i> All folders</a>
            @if($dept)
                <i data-lucide="chevron-right" class="text-slate-300"></i>
                <a href="{{ route('documents', ['dept' => $dept->id]) }}" class="rounded px-1.5 py-0.5 {{ $level === 'dept' ? 'font-semibold text-slate-800' : 'text-slate-500 hover:bg-slate-100' }}">{{ $dept->name }}</a>
            @endif
            @if($current)
                <i data-lucide="chevron-right" class="text-slate-300"></i>
                <span class="rounded px-1.5 py-0.5 font-semibold text-slate-800">{{ $current->name }}</span>
            @endif
        </nav>
        @if($current)
            <span class="text-xs text-slate-400">{{ $total }} {{ Str::plural('file', $total) }}</span>
            <a href="{{ route('projects.show', $current) }}" class="btn btn-primary btn-sm"><i data-lucide="upload"></i> Upload &amp; search</a>
            @if($u->isClientAdmin())<a href="{{ route('projects.gallery', $current) }}" class="btn btn-ghost btn-sm"><i data-lucide="layout-grid"></i> Gallery</a>@endif
        @endif
    </div>

    <div class="grid grid-cols-2 gap-2 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6" style="min-height:50vh;align-content:start">
        @if($level === 'root')
            @foreach($depts as $d)
                <a href="{{ route('documents', ['dept' => $d->id]) }}" class="group flex flex-col items-center rounded-xl p-3 text-center transition hover:bg-brand-soft" title="{{ $d->name }}">
                    {!! $folder !!}
                    <span class="mt-2 w-full truncate text-sm font-medium text-slate-800 group-hover:text-brand">{{ $d->name }}</span>
                    <span class="text-xs text-slate-400">{{ $d->projects->count() }} {{ Str::plural('folder', $d->projects->count()) }} · {{ $d->docs }} {{ Str::plural('file', $d->docs) }}</span>
                </a>
            @endforeach
        @elseif($level === 'dept')
            @foreach($dept->projects as $p)
                @php($s = $stats[$p->id] ?? null)
                <a href="{{ route('documents', ['project' => $p->id]) }}" class="group relative flex flex-col items-center rounded-xl p-3 text-center transition hover:bg-brand-soft" title="{{ $p->name }}">
                    {!! $folder !!}
                    @if(($s->pending ?? 0) > 0)<span class="absolute right-3 top-2 rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white" title="Waiting to be indexed">{{ $s->pending }}</span>@endif
                    <span class="mt-2 w-full truncate text-sm font-medium text-slate-800 group-hover:text-brand">{{ $p->name }}</span>
                    <span class="text-xs text-slate-400">{{ $s->total ?? 0 }} {{ Str::plural('file', $s->total ?? 0) }}</span>
                </a>
            @endforeach
        @else
            @forelse($files as $f)
                <a href="{{ route('documents.show', $f) }}" class="group relative flex flex-col items-center rounded-xl p-3 text-center transition hover:bg-brand-soft" title="{{ $f->original_name }} · {{ $f->humanSize() }}">
                    @include('documents._ficon', ['name' => $f->original_name])
                    @if($f->status === 'unindexed')<span class="absolute right-3 top-2 size-2.5 rounded-full bg-amber-500" title="Waiting to be indexed"></span>@endif
                    <span class="mt-2 w-full break-words text-xs font-medium leading-4 text-slate-800 group-hover:text-brand" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $f->original_name }}</span>
                    <span class="text-[11px] text-slate-400">{{ $f->humanSize() }} · {{ $f->created_at->format('d M') }}</span>
                </a>
            @empty
                <p class="col-span-full py-16 text-center text-sm text-slate-400">This folder is empty.</p>
            @endforelse
        @endif
    </div>
    @if($current && $total > $files->count())
        <p class="border-t border-slate-100 px-4 py-3 text-center text-xs text-slate-500">Showing the first {{ $files->count() }} of {{ $total }}. <a href="{{ route('projects.show', $current) }}" class="font-medium text-brand hover:underline">Open the project</a> to search and page through all of them.</p>
    @endif
</div>
@endif
@endsection

@extends('layouts.app')
@section('title', $project->name.' · Gallery')

@php
    // icon, colour and label per file type
    $kind = function ($d) {
        $ext = strtolower(pathinfo($d->original_name, PATHINFO_EXTENSION));

        return match (true) {
            $ext === 'pdf' => ['file-text', 'text-red-600 bg-red-50', 'PDF'],
            in_array($ext, ['doc', 'docx', 'rtf'], true) => ['file-text', 'text-blue-600 bg-blue-50', 'Word'],
            in_array($ext, ['xls', 'xlsx', 'csv'], true) => ['file-spreadsheet', 'text-emerald-600 bg-emerald-50', 'Excel'],
            in_array($ext, ['ppt', 'pptx'], true) => ['presentation', 'text-orange-600 bg-orange-50', 'PowerPoint'],
            in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'tif', 'tiff', 'webp'], true) => ['file-image', 'text-purple-600 bg-purple-50', 'Image'],
            default => ['file-text', 'text-slate-600 bg-slate-100', strtoupper($ext ?: 'File')],
        };
    };
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><a href="{{ route('projects.show', $project) }}" class="text-sm text-brand hover:underline">← {{ $project->name }}</a>
        <h1 class="mt-1 text-2xl font-bold">Gallery</h1>
        <p class="text-sm text-slate-500">{{ $project->department->name }} · {{ $documents->total() }} {{ str('document')->plural($documents->total()) }}. Click a card to open it; hover the stack to see older versions.</p></div>
    <a href="{{ route('projects.show', $project) }}" class="btn btn-ghost"><i data-lucide="list"></i> View as list</a>
</div>

@if($documents->isEmpty())
    <div class="card card-pad py-16 text-center text-sm text-slate-400">No documents in this project yet.</div>
@else
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @foreach($documents as $d)
        @php([$icon, $tone, $label] = $kind($d))
        @php($vs = $versions[$d->id] ?? collect())
        <div class="card group relative flex flex-col overflow-visible transition hover:shadow-lg">
            {{-- Picture --}}
            <div class="relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-t-xl bg-slate-100">
                <span class="absolute inset-0 flex items-center justify-center {{ $tone }}"><i data-lucide="{{ $icon }}" class="!size-14 opacity-70"></i></span>
                @if($d->isPdf() || $d->isImage())
                    <img src="{{ route('pages.show', [$d, 1]) }}" alt="" loading="lazy" onerror="this.remove()" class="relative h-full w-full bg-white object-cover object-top">
                @endif
                <a href="{{ route('documents.show', $d) }}" class="absolute inset-0 z-10" aria-label="Open {{ $d->original_name }}"></a>
            </div>

            {{-- Information --}}
            <div class="flex flex-1 items-start gap-3 p-4">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg {{ $tone }}" title="{{ $label }}"><i data-lucide="{{ $icon }}"></i></span>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('documents.show', $d) }}" class="block truncate text-sm font-semibold hover:text-brand" title="{{ $d->original_name }}">{{ $d->original_name }}</a>
                    <div class="mt-0.5 text-xs text-slate-500">{{ $label }} · {{ $d->humanSize() }} · {{ $d->created_at->format('d M Y') }}</div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <span class="badge {{ $d->status === 'indexed' ? 'badge-green' : 'badge-amber' }}">{{ ucfirst($d->status) }}</span>
                        @if($d->doc_type)<span class="badge badge-slate">{{ $d->doc_type }}</span>@endif
                        @if($d->legal_hold)<span class="badge badge-red">Legal hold</span>@endif
                    </div>
                </div>

                {{-- Version stack: hover to see older files --}}
                @if($vs->isNotEmpty())
                    <div class="group/v relative z-20 shrink-0">
                        <button type="button" class="relative inline-flex size-10 items-center justify-center" aria-label="{{ $vs->count() + 1 }} versions">
                            <span class="absolute left-1.5 top-0.5 size-7 rotate-6 rounded-md border border-slate-300 bg-slate-100"></span>
                            <span class="absolute left-0.5 top-1 size-7 -rotate-3 rounded-md border border-slate-300 bg-white"></span>
                            <span class="relative inline-flex size-7 items-center justify-center rounded-md border border-brand bg-brand-soft text-brand"><i data-lucide="layers" class="!size-4"></i></span>
                            <span class="absolute -right-1 -top-1 min-w-4 rounded-full bg-brand px-1 text-center text-[10px] font-bold leading-4 text-white">{{ $vs->count() + 1 }}</span>
                        </button>
                        <div class="invisible absolute bottom-full right-0 z-30 mb-2 w-64 rounded-xl border border-slate-200 bg-white p-2 opacity-0 shadow-xl transition group-hover/v:visible group-hover/v:opacity-100 group-focus-within/v:visible group-focus-within/v:opacity-100">
                            <div class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Versions</div>
                            <a href="{{ route('documents.show', $d) }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-slate-50"><i data-lucide="{{ $icon }}" class="!size-4 {{ explode(' ', $tone)[0] }}"></i><span class="flex-1 font-medium">Current</span><span class="text-slate-400">{{ $d->humanSize() }}</span></a>
                            @foreach($vs as $v)
                                @php($vExt = strtolower(pathinfo($d->original_name, PATHINFO_EXTENSION)))
                                @if($canDownload)
                                    <a data-no-ajax href="{{ route('versions.download', $v) }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-slate-50"><i data-lucide="{{ $icon }}" class="!size-4 text-slate-400"></i><span class="flex-1">Version {{ $v->version_no }} <span class="text-slate-400">· {{ $v->replaced_at->format('d M Y') }}</span></span><i data-lucide="download" class="!size-3.5 text-slate-400"></i></a>
                                @else
                                    <div class="flex items-center gap-2 px-2 py-1.5 text-xs text-slate-500"><i data-lucide="{{ $icon }}" class="!size-4 text-slate-400"></i><span class="flex-1">Version {{ $v->version_no }} · {{ $v->replaced_at->format('d M Y') }}</span></div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
<div class="mt-6">{{ $documents->links() }}</div>
@endif
@endsection

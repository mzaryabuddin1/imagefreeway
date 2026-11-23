@extends('layouts.app')
@section('title', $project->name)

@php
    $u = auth()->user();
    $canUpload = $u->canOn($project, 'can_upload');
    $canIndex = $u->canOn($project, 'can_index');
    $cols = $fields->where('show_in_results', true);
    $sortUrl = fn ($key) => request()->fullUrlWithQuery(['sort' => $key, 'dir' => ($sort === $key && $dir === 'asc') ? 'desc' : 'asc']);
    $arrow = fn ($key) => $sort === $key ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><a href="{{ route('documents') }}" class="text-sm text-brand hover:underline">← Documents</a>
        <h1 class="mt-1 text-2xl font-bold">{{ $project->name }}</h1>
        <p class="text-sm text-slate-500">{{ $project->department->name }}</p></div>
    <div class="flex flex-wrap gap-2">
        @if($canIndex && $pending > 0)<a href="{{ route('projects.queue', $project) }}" class="btn btn-primary"><i data-lucide="pencil"></i> Index {{ $pending }} waiting</a>@endif
        @if($u->isClientAdmin())<a href="{{ route('projects.gallery', $project) }}" class="btn btn-ghost"><i data-lucide="layout-grid"></i> View as gallery</a>@endif
        @if($u->isClientAdmin())<a href="{{ route('projects.settings', $project) }}" class="btn btn-ghost"><i data-lucide="settings"></i> Fields &amp; access</a>@endif
    </div>
</div>

@if($canUpload)
<div data-dropzone data-url="{{ route('projects.upload', $project) }}" class="card mb-6 border-2 border-dashed border-slate-300 p-6 text-center transition [&.drag]:border-brand [&.drag]:bg-brand-soft">
    <i data-lucide="upload" class="mx-auto !size-8 text-slate-400"></i>
    <p class="mt-2 text-sm font-medium">Drag files here, or
        <label class="cursor-pointer text-brand hover:underline">choose files<input type="file" multiple data-upload class="hidden"></label></p>
    <p class="text-xs text-slate-400">PDF, Office files, images, text — up to 100 MB each. Many files at once is fine; one failure never stops the rest.</p>
    <ul id="upload-list" class="mx-auto mt-4 max-w-xl space-y-2 text-left"></ul>
</div>
@endif

{{-- Search --}}
<form method="get" action="{{ route('projects.show', $project) }}" class="card card-pad mb-4">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><label class="label">File name</label><input name="name" value="{{ request('name') }}" class="input"></div>
        @foreach($fields->where('searchable', true) as $f)
            <div>
                <label class="label">{{ $f->label }}</label>
                @if(in_array($f->type, ['date', 'number', 'amount']))
                    <div class="grid grid-cols-2 gap-1">
                        <input type="{{ $f->type === 'date' ? 'date' : 'number' }}" step="any" name="from[{{ $f->key }}]" value="{{ request("from.{$f->key}") }}" class="input min-w-0" placeholder="From" aria-label="{{ $f->label }} from">
                        <input type="{{ $f->type === 'date' ? 'date' : 'number' }}" step="any" name="to[{{ $f->key }}]" value="{{ request("to.{$f->key}") }}" class="input min-w-0" placeholder="To" aria-label="{{ $f->label }} to">
                    </div>
                @elseif($f->type === 'yesno')
                    <select name="f[{{ $f->key }}]" class="input"><option value="">Any</option><option @selected(request("f.{$f->key}")==='Yes')>Yes</option><option @selected(request("f.{$f->key}")==='No')>No</option></select>
                @elseif($f->type === 'picklist')
                    <select name="f[{{ $f->key }}]" class="input"><option value="">Any</option>@foreach($f->options ?? [] as $o)<option @selected(request("f.{$f->key}")===$o)>{{ $o }}</option>@endforeach</select>
                @else
                    <input name="f[{{ $f->key }}]" value="{{ request("f.{$f->key}") }}" class="input">
                @endif
            </div>
        @endforeach
        <div><label class="label">Words inside the document</label><input name="text" value="{{ request('text') }}" class="input" placeholder="Text / OCR"></div>
        <div><label class="label">Status</label><select name="status" class="input"><option value="">Any</option><option value="indexed" @selected(request('status')==='indexed')>Indexed</option><option value="unindexed" @selected(request('status')==='unindexed')>Unindexed</option></select></div>
    </div>
    <div class="mt-4 flex gap-2"><button class="btn btn-primary"><i data-lucide="search"></i> Search</button><a href="{{ route('projects.show', $project) }}" class="btn btn-ghost">Clear</a></div>
</form>

<div class="card overflow-x-auto">
    <div class="border-b border-slate-100 px-4 py-3 text-sm text-slate-500">{{ $documents->total() }} {{ Str::plural('document', $documents->total()) }}</div>
    <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr>
            <th class="th"><a href="{{ $sortUrl('name') }}">Document{{ $arrow('name') }}</a></th>
            @foreach($cols as $f)<th class="th"><a href="{{ $sortUrl($f->key) }}">{{ $f->label }}{{ $arrow($f->key) }}</a></th>@endforeach
            <th class="th"><a href="{{ $sortUrl('status') }}">Status{{ $arrow('status') }}</a></th>
            <th class="th"><a href="{{ $sortUrl('created') }}">Added{{ $arrow('created') }}</a></th>
        </tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($documents as $d)
            <tr class="hover:bg-slate-50">
                <td class="td"><a href="{{ route('documents.show', $d) }}" class="flex items-center gap-2 font-medium text-brand hover:underline"><i data-lucide="file-text"></i> {{ $d->original_name }}</a><span class="text-xs text-slate-400">{{ $d->humanSize() }}</span></td>
                @foreach($cols as $f)<td class="td text-slate-700">{{ $d->index_values[$f->key] ?? '—' }}</td>@endforeach
                <td class="td"><span class="badge {{ $d->status === 'indexed' ? 'badge-green' : 'badge-amber' }}">{{ ucfirst($d->status) }}</span></td>
                <td class="td whitespace-nowrap text-slate-500">{{ $d->created_at->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $cols->count() + 3 }}" class="px-4 py-12 text-center text-sm text-slate-400">No documents match.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $documents->links() }}</div>
@endsection

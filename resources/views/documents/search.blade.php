@extends('layouts.app')
@section('title', 'Search')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Search</h1>
    <p class="text-sm text-slate-500">Across every project you can open — index values, file names and the words inside documents.</p></div>

<form method="get" action="{{ route('search') }}" class="mb-6 flex max-w-2xl gap-2">
    <input name="q" value="{{ $q }}" class="input" placeholder="Invoice number, name, a phrase from the document…" autofocus>
    <button class="btn btn-primary"><i data-lucide="search"></i> Search</button>
</form>

@if($q !== '')
<div class="card divide-y divide-slate-100">
    <div class="px-5 py-3 text-sm text-slate-500">{{ $results->total() }} {{ Str::plural('result', $results->total()) }} for “{{ $q }}”</div>
    @forelse($results as $d)
        <a href="{{ route('documents.show', $d) }}" class="block px-5 py-4 hover:bg-slate-50">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="flex items-center gap-2 font-medium text-brand"><i data-lucide="file-text"></i> {{ $d->original_name }}</span>
                <span class="text-xs text-slate-500">{{ $d->project->department->name }} › {{ $d->project->name }}</span>
            </div>
            @if($d->search_text)<p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($d->search_text, 140) }}</p>@endif
            @if($d->snippet)<p class="mt-1 text-xs italic text-slate-400">{{ $d->snippet }}</p>@endif
        </a>
    @empty
        <p class="px-5 py-12 text-center text-sm text-slate-400">Nothing found.</p>
    @endforelse
</div>
<div class="mt-4">{{ $results->links() }}</div>
@endif
@endsection

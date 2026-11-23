@extends('layouts.app')
@section('title', 'Quarantine')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Quarantine</h1>
    <p class="text-sm text-slate-500">Files that failed the virus scan are kept here, not filed. Release one only if you are sure it is safe.</p></div>

<section class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Held files <span class="ml-2 text-xs font-normal text-slate-500">{{ $held->count() }}</span></div>
    @forelse($held as $q)
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 last:border-0">
            <div class="min-w-0 flex-1">
                <div class="truncate font-medium">{{ $q->original_name }}</div>
                <div class="text-xs text-slate-500">
                    <span class="badge badge-red">{{ $q->threat }}</span>
                    · {{ ucfirst($q->source) }}@if($q->source_detail) ({{ $q->source_detail }})@elseif($q->uploader) ({{ $q->uploader->name }})@endif
                    · {{ $q->project?->name ?? 'No project' }} · {{ $q->created_at->format('d M Y H:i') }}
                </div>
            </div>
            <form method="post" action="{{ route('quarantine.release', $q) }}" data-confirm-title="Release this file?" data-confirm="It will be filed in {{ $q->project?->name ?? 'the project' }} even though it failed the scan." data-confirm-button="Yes, release" data-danger>@csrf<button class="btn btn-ghost btn-sm">Release</button></form>
            <form method="post" action="{{ route('quarantine.destroy', $q) }}" data-confirm-title="Destroy this file?" data-confirm="It is deleted permanently and cannot be recovered." data-confirm-button="Yes, destroy" data-danger>@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600">Destroy</button></form>
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-400">Nothing in quarantine.</p>
    @endforelse
</section>

@if($history->isNotEmpty())
<section class="card">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Recently handled</div>
    <ul class="divide-y divide-slate-100 text-sm">
        @foreach($history as $q)
            <li class="flex justify-between gap-3 px-5 py-2.5"><span class="truncate">{{ $q->original_name }} <span class="text-slate-400">· {{ $q->threat }}</span></span>
                <span class="shrink-0 text-xs text-slate-500">{{ ucfirst($q->status) }} {{ $q->resolved_at?->format('d M Y') }}</span></li>
        @endforeach
    </ul>
</section>
@endif
@endsection

@extends('layouts.app')
@section('title', 'Blueprints')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Setup blueprints</h1>
    <p class="text-sm text-slate-500">A complete client setup saved once and reused. Save one from a client's Support page; choose it when you create a new client.</p></div>

<div class="card">
    @forelse($blueprints as $b)
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-4 last:border-0">
            <div class="min-w-0 flex-1">
                <a href="{{ route('blueprints.show', $b) }}" class="font-semibold text-brand hover:underline">{{ $b->name }}</a>
                <span class="badge badge-blue">v{{ $b->version }}</span> @if($b->industry)<span class="badge badge-slate">{{ $b->industry }}</span>@endif
                <div class="mt-0.5 text-xs text-slate-500">{{ $b->summary['departments'] }} departments · {{ $b->summary['projects'] }} projects · {{ $b->summary['fields'] }} fields · {{ $b->summary['workflows'] }} workflows · {{ $b->summary['forms'] }} forms · {{ $b->summary['doc_types'] }} document types · saved {{ $b->created_at->format('d M Y') }}</div>
            </div>
            <a href="{{ route('clients.create', ['blueprint' => $b->id]) }}" class="btn btn-primary btn-sm"><i data-lucide="plus"></i> New client from this</a>
            <form method="post" action="{{ route('blueprints.destroy', $b) }}" data-danger data-confirm-title="Delete this blueprint?" data-confirm="Clients already made from it are not affected." data-confirm-button="Yes, delete">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600">Delete</button></form>
        </div>
    @empty
        <p class="px-5 py-12 text-center text-sm text-slate-400">No blueprints yet. Open a client in the Support console and press “Save as blueprint”.</p>
    @endforelse
</div>
@endsection

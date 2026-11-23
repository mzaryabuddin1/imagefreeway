@extends('layouts.app')
@section('title', $client->name.' · Support')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3"><div><a href="{{ route('support') }}" class="text-sm text-brand hover:underline">← Support console</a>
    <h1 class="text-2xl font-bold">{{ $client->name }}</h1>
    <p class="text-sm text-slate-500">{{ $client->domain ?: 'No domain' }} · {{ ucfirst($client->status) }} · read-only view</p></div>
    <a data-no-ajax href="{{ route('support.export', $client) }}" class="btn btn-ghost"><i data-lucide="download"></i> Export all data (zip)</a></div>

@if($client->blueprint_id)<p class="mb-3 text-sm text-slate-500">Created from blueprint <a class="text-brand hover:underline" href="{{ route('blueprints.show', $client->blueprint_id) }}">#{{ $client->blueprint_id }}</a>.</p>@endif
<details class="card card-pad mb-6">
    <summary class="cursor-pointer text-sm font-semibold">Save this client's setup as a blueprint</summary>
    <form method="post" action="{{ route('blueprints.store', $client) }}" class="mt-4 grid items-end gap-4 sm:grid-cols-3" data-confirm-title="Save as blueprint?" data-confirm="A new version is saved with the departments, projects, fields, workflows, forms and document types. No people or documents." data-confirm-button="Yes, save">
        @csrf
        <div><label class="label">Blueprint name <span class="text-red-500">*</span></label><input name="name" class="input" required maxlength="120" placeholder="Dental practice"></div>
        <div><label class="label">Industry</label><input name="industry" class="input" maxlength="80" placeholder="Healthcare"></div>
        <div><label class="label">Notes</label><input name="notes" class="input" maxlength="1000"></div>
        <div class="sm:col-span-3"><button class="btn btn-primary btn-sm">Save blueprint</button> <span class="text-xs text-slate-500">Using an existing name saves the next version.</span></div>
    </form>
</details>

@if($row)
<div class="mb-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
    @foreach([['Users', $row->users], ['Documents', number_format($row->documents)], ['Storage', $row->storage_mb.' MB'], ['OCR failed', $row->ocr_failed], ['Overdue items', $row->overdue], ['Quarantined', $row->quarantined]] as [$l, $v])
        <div class="card card-pad"><div class="text-xs text-slate-500">{{ $l }}</div><div class="mt-1 text-xl font-bold {{ in_array($l, ['OCR failed','Overdue items','Quarantined']) && $v ? 'text-red-600' : '' }}">{{ $v }}</div></div>
    @endforeach
</div>
<p class="mb-6 text-sm text-slate-500">Last login {{ $row->last_login?->diffForHumans() ?? 'never' }} · last upload {{ $row->last_upload?->diffForHumans() ?? 'never' }}</p>
@endif

<section class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">See the product as a user <span class="ml-2 text-xs font-normal text-slate-500">read-only; recorded in this client's audit trail</span></div>
    <ul class="divide-y divide-slate-100 text-sm">
        @foreach($people as $p)
            <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                <div class="min-w-0"><span class="font-medium">{{ $p->name }}</span> <span class="text-slate-400">· {{ $p->roleLabel() }} · {{ $p->email }}</span>
                    @if($p->status !== 'active')<span class="badge badge-slate">{{ ucfirst($p->status) }}</span>@endif</div>
                @if($p->status === 'active')
                <form method="post" action="{{ route('support.start', [$client, $p]) }}" data-confirm-title="View as {{ $p->name }}?" data-confirm="You will see exactly what they see. It is read-only and is written to {{ $client->name }}'s audit trail." data-confirm-button="Yes, view">@csrf<button class="btn btn-ghost btn-sm"><i data-lucide="eye"></i> View as</button></form>
                @endif
            </li>
        @endforeach
    </ul>
</section>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card">
        <div class="border-b border-slate-100 px-5 py-3 font-semibold">Failed OCR</div>
        <ul class="divide-y divide-slate-100 text-sm">
            @forelse($failed as $d)<li class="flex justify-between px-5 py-2.5"><span class="truncate">{{ $d->original_name }}</span><span class="text-xs text-slate-400">{{ $d->created_at->format('d M Y') }}</span></li>
            @empty<li class="px-5 py-8 text-center text-slate-400">None.</li>@endforelse
        </ul>
    </section>
    <section class="card">
        <div class="border-b border-slate-100 px-5 py-3 font-semibold">Recent activity</div>
        <ul class="divide-y divide-slate-100 text-sm">
            @forelse($log as $a)<li class="px-5 py-2.5"><div>{{ $a->description ?: $a->action }}</div><div class="text-xs text-slate-400">{{ $a->user?->name ?? 'System' }} · {{ $a->created_at->format('d M Y H:i') }}</div></li>
            @empty<li class="px-5 py-8 text-center text-slate-400">No activity yet.</li>@endforelse
        </ul>
    </section>
</div>
@endsection

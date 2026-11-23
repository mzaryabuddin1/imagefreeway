@extends('layouts.app')
@section('title', 'Support console')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Support console</h1><p class="text-sm text-slate-500">Every client with its usage and health at a glance.</p></div>
    <a data-no-ajax href="{{ route('support', ['export' => 'csv', 'q' => $q]) }}" class="btn btn-ghost"><i data-lucide="download"></i> Export usage CSV</a>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-4">
    @foreach([['Clients', $totals['clients']], ['Documents', number_format($totals['documents'])], ['Storage', $totals['storage_mb'].' MB'], ['Need attention', $totals['attention']]] as [$l, $v])
        <div class="card card-pad"><div class="text-xs text-slate-500">{{ $l }}</div><div class="mt-1 text-2xl font-bold {{ $l === 'Need attention' && $v ? 'text-red-600' : '' }}">{{ $v }}</div></div>
    @endforeach
</div>

<form method="get" action="{{ route('support') }}" data-no-confirm class="mb-4 flex gap-2">
    <input name="q" value="{{ $q }}" placeholder="Search name or domain…" class="input max-w-xs">
    <button class="btn btn-ghost"><i data-lucide="search"></i> Search</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50"><tr><th class="th">Client</th><th class="th">Users</th><th class="th">Documents</th><th class="th">Storage</th><th class="th">Last login</th><th class="th">Last upload</th><th class="th">Health</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($rows as $r)
            <tr>
                <td class="td"><a href="{{ route('support.show', $r) }}" class="font-medium text-brand hover:underline">{{ $r->name }}</a>
                    <div class="text-xs text-slate-400">{{ $r->domain ?: 'no domain' }} · {{ ucfirst($r->status) }}</div></td>
                <td class="td">{{ $r->users }}</td>
                <td class="td">{{ number_format($r->documents) }}</td>
                <td class="td">{{ $r->storage_mb }} MB</td>
                <td class="td text-slate-500">{{ $r->last_login?->diffForHumans() ?? '—' }}</td>
                <td class="td text-slate-500">{{ $r->last_upload?->diffForHumans() ?? '—' }}</td>
                <td class="td">
                    @if($r->needs_attention)
                        @if($r->ocr_failed)<span class="badge badge-amber">{{ $r->ocr_failed }} OCR failed</span>@endif
                        @if($r->overdue)<span class="badge badge-amber">{{ $r->overdue }} overdue</span>@endif
                        @if($r->quarantined)<span class="badge badge-red">{{ $r->quarantined }} quarantined</span>@endif
                    @else<span class="badge badge-green">Healthy</span>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No clients found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

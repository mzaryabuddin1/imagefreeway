@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Audit Log</h1><p class="text-sm text-slate-500">Who did what, and when. Entries cannot be edited.</p></div>

<form method="get" action="{{ route('audit') }}" class="mb-4 flex gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Search actions…" class="input max-w-xs">
    <button class="btn btn-ghost"><i data-lucide="search"></i> Search</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">When</th><th class="th">User</th>@if(auth()->user()->isSuperAdmin())<th class="th">Client</th>@endif<th class="th">Action</th><th class="th">Details</th><th class="th">IP</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($logs as $log)
            <tr>
                <td class="td whitespace-nowrap text-slate-600" title="{{ $log->created_at }}">{{ $log->created_at->format('d M Y H:i') }}</td>
                <td class="td">{{ $log->user?->name ?? 'System' }}</td>
                @if(auth()->user()->isSuperAdmin())<td class="td text-slate-600">{{ $log->client?->name ?? '—' }}</td>@endif
                <td class="td"><span class="badge badge-slate">{{ $log->action }}</span></td>
                <td class="td text-slate-600">
                    {{ $log->description }}
                    @if(!empty($log->meta['before']) || !empty($log->meta['after']))
                        <details class="mt-1 text-xs"><summary class="cursor-pointer text-brand">Before / after</summary>
                            <pre class="mt-1 overflow-x-auto rounded bg-slate-50 p-2">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                    @endif
                </td>
                <td class="td font-mono text-xs text-slate-500">{{ $log->ip }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-slate-400">No activity recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection

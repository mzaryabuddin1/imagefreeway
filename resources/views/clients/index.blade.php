@extends('layouts.app')
@section('title', 'Clients')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Clients</h1><p class="text-sm text-slate-500">Every client runs the same software; settings decide the difference.</p></div>
    <a href="{{ route('clients.create') }}" class="btn btn-primary"><i data-lucide="plus"></i> New client</a>
</div>

<form method="get" action="{{ route('clients.index') }}" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Search name or domain…" class="input max-w-xs">
    <select name="status" class="input w-40">
        <option value="">All statuses</option>
        @foreach(['active','suspended','archived'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach
    </select>
    <button class="btn btn-ghost"><i data-lucide="search"></i> Filter</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">Client</th><th class="th">Domain</th><th class="th">Users</th><th class="th">Modules</th><th class="th">Status</th><th class="th text-right">Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($clients as $c)
            <tr>
                <td class="td"><div class="flex items-center gap-3">
                    <span class="size-3 rounded-full" style="background:{{ $c->primary_color ?: \App\Models\Client::DEFAULT_PRIMARY }}"></span>
                    <div><p class="font-semibold">{{ $c->name }}</p><p class="text-xs text-slate-500">{{ $c->slug }}</p></div></div></td>
                <td class="td text-slate-600">{{ $c->domain ?: '—' }}</td>
                <td class="td">{{ $c->users_count }}</td>
                <td class="td text-slate-600">{{ collect($c->modules ?? [])->filter()->count() }} of {{ count(\App\Models\Client::MODULES) }}</td>
                <td class="td"><span class="badge {{ ['active'=>'badge-green','suspended'=>'badge-amber','archived'=>'badge-slate'][$c->status] }}">{{ ucfirst($c->status) }}</span></td>
                <td class="td"><div class="flex justify-end gap-1">
                    <a href="{{ route('clients.edit', $c) }}" class="icon-btn" title="Edit"><i data-lucide="pencil"></i></a>
                    @if($c->status === 'active')
                        <form method="post" action="{{ route('clients.status', $c) }}" data-danger data-confirm="{{ $c->name }} will be blocked from signing in until reactivated." data-confirm-title="Suspend this client?" data-confirm-button="Yes, suspend">
                            @csrf <input type="hidden" name="status" value="suspended"><button class="icon-btn" title="Suspend"><i data-lucide="pause"></i></button></form>
                    @else
                        <form method="post" action="{{ route('clients.status', $c) }}" data-confirm="{{ $c->name }} will be able to sign in again." data-confirm-title="Reactivate this client?" data-confirm-button="Yes, reactivate">
                            @csrf <input type="hidden" name="status" value="active"><button class="icon-btn" title="Reactivate"><i data-lucide="play"></i></button></form>
                    @endif
                    @if($c->status !== 'archived')
                        <form method="post" action="{{ route('clients.status', $c) }}" data-danger data-confirm="Archived clients cannot sign in. You can reactivate them later." data-confirm-title="Archive {{ $c->name }}?" data-confirm-button="Yes, archive">
                            @csrf <input type="hidden" name="status" value="archived"><button class="icon-btn" title="Archive"><i data-lucide="archive"></i></button></form>
                    @endif
                    <form method="post" action="{{ route('clients.destroy', $c) }}" data-danger data-confirm="This deletes the client record. This cannot be undone from the screen." data-confirm-title="Delete {{ $c->name }}?" data-confirm-button="Yes, delete">
                        @csrf @method('DELETE')<button class="icon-btn hover:!text-red-600" title="Delete"><i data-lucide="trash-2"></i></button></form>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-slate-400">No clients found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $clients->links() }}</div>
@endsection

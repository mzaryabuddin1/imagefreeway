@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Users</h1><p class="text-sm text-slate-500">Create people, set their role and archive them when they leave.</p></div>
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i data-lucide="user-plus"></i> New user</a>
</div>

<form method="get" action="{{ route('users.index') }}" class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Search name or email…" class="input max-w-xs">
    @if(auth()->user()->isSuperAdmin())
        <select name="client" class="input w-52"><option value="">All clients</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client')==$c->id)>{{ $c->name }}</option>@endforeach</select>
    @endif
    <select name="status" class="input w-40"><option value="">All statuses</option>
        @foreach(['active','inactive','archived'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select>
    <button class="btn btn-ghost"><i data-lucide="search"></i> Filter</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">User</th>@if(auth()->user()->isSuperAdmin())<th class="th">Client</th>@endif<th class="th">Role</th><th class="th">Last sign-in</th><th class="th">Status</th><th class="th text-right">Actions</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($users as $u)
            <tr>
                <td class="td"><p class="font-semibold">{{ $u->name }}</p><p class="text-xs text-slate-500">{{ $u->email }}</p></td>
                @if(auth()->user()->isSuperAdmin())<td class="td text-slate-600">{{ $u->client?->name ?? 'Platform' }}</td>@endif
                <td class="td"><span class="badge badge-blue">{{ $u->roleLabel() }}</span></td>
                <td class="td text-slate-600">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="td"><span class="badge {{ ['active'=>'badge-green','inactive'=>'badge-amber','archived'=>'badge-slate'][$u->status] }}">{{ ucfirst($u->status) }}</span></td>
                <td class="td"><div class="flex justify-end gap-1">
                    <a href="{{ route('users.edit', $u) }}" class="icon-btn" title="Edit"><i data-lucide="pencil"></i></a>
                    @if($u->id !== auth()->id())
                        @if($u->status === 'archived')
                            <form method="post" action="{{ route('users.status', $u) }}" data-confirm="{{ $u->name }} will be able to sign in again." data-confirm-title="Restore this user?" data-confirm-button="Yes, restore">
                                @csrf <input type="hidden" name="status" value="active"><button class="icon-btn" title="Restore"><i data-lucide="archive-restore"></i></button></form>
                        @else
                            <form method="post" action="{{ route('users.status', $u) }}" data-danger data-confirm="{{ $u->name }} will no longer be able to sign in. Their history stays." data-confirm-title="Archive this user?" data-confirm-button="Yes, archive">
                                @csrf <input type="hidden" name="status" value="archived"><button class="icon-btn" title="Archive"><i data-lucide="archive"></i></button></form>
                        @endif
                        <form method="post" action="{{ route('users.destroy', $u) }}" data-danger data-confirm="This removes {{ $u->name }} from the list." data-confirm-title="Delete this user?" data-confirm-button="Yes, delete">
                            @csrf @method('DELETE')<button class="icon-btn hover:!text-red-600" title="Delete"><i data-lucide="trash-2"></i></button></form>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-slate-400">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection

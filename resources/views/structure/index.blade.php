@extends('layouts.app')
@section('title', 'Departments & Projects')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold">Departments &amp; Projects</h1>
    <p class="text-sm text-slate-500">A project holds documents of one kind (for example Billing or Personnel). Index fields and access are set per project.</p>
</div>

<form method="post" action="{{ route('departments.store') }}" class="card card-pad mb-6 flex flex-wrap items-end gap-3">
    @csrf
    <div class="min-w-64 flex-1"><label class="label">New department</label><input name="name" class="input" placeholder="e.g. Finance, Human Resources" required></div>
    <button class="btn btn-primary"><i data-lucide="plus"></i> Add department</button>
</form>

<div class="space-y-5">
@forelse($departments as $d)
    <section class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-4">
            <form method="post" action="{{ route('departments.update', $d) }}" class="flex flex-1 items-center gap-2">
                @csrf @method('PUT')
                <input name="name" value="{{ $d->name }}" class="input max-w-xs font-semibold" required>
                <button class="icon-btn" title="Rename"><i data-lucide="check"></i></button>
            </form>
            <form method="post" action="{{ route('departments.destroy', $d) }}" data-danger data-confirm-title="Delete {{ $d->name }}?" data-confirm="Only empty departments can be deleted." data-confirm-button="Yes, delete">
                @csrf @method('DELETE')<button class="icon-btn hover:!text-red-600" title="Delete department"><i data-lucide="trash-2"></i></button></form>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse($d->projects as $p)
                @php($c = $counts[$p->id] ?? null)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <div><a href="{{ route('projects.settings', $p) }}" class="font-semibold hover:text-brand">{{ $p->name }}</a>
                        <p class="text-xs text-slate-500">{{ $p->fields_count }} index fields · {{ $c->total ?? 0 }} documents @if(($c->pending ?? 0) > 0)· <span class="text-amber-600">{{ $c->pending }} unindexed</span>@endif</p></div>
                    <div class="flex gap-2">
                        <a href="{{ route('projects.show', $p) }}" class="btn btn-ghost btn-sm"><i data-lucide="folder-open"></i> Open</a>
                        <a href="{{ route('projects.settings', $p) }}" class="btn btn-ghost btn-sm"><i data-lucide="settings"></i> Fields &amp; access</a>
                    </div>
                </li>
            @empty
                <li class="px-5 py-5 text-sm text-slate-400">No projects yet.</li>
            @endforelse
        </ul>

        <form method="post" action="{{ route('projects.store', $d) }}" class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
            @csrf
            <input name="name" class="input max-w-xs bg-white" placeholder="New project in {{ $d->name }}" required>
            <button class="btn btn-ghost btn-sm"><i data-lucide="plus"></i> Add project</button>
        </form>
    </section>
@empty
    <div class="card card-pad text-center text-sm text-slate-400">No departments yet. Add your first one above.</div>
@endforelse
</div>
@endsection

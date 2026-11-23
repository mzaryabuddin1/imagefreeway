@extends('layouts.app')
@section('title', $project->name.' settings')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><a href="{{ route('structure') }}" class="text-sm text-brand hover:underline">← Departments &amp; Projects</a>
        <h1 class="mt-1 text-2xl font-bold">{{ $project->name }}</h1>
        <p class="text-sm text-slate-500">{{ $project->department->name }}</p></div>
    <a href="{{ route('projects.show', $project) }}" class="btn btn-ghost"><i data-lucide="folder-open"></i> Open documents</a>
</div>

{{-- Settings --}}
<form method="post" action="{{ route('projects.update', $project) }}" class="card card-pad mb-6">
    @csrf @method('PUT')
    <h2 class="mb-4 font-semibold">Project settings</h2>
    <div class="grid gap-5 sm:grid-cols-3">
        <div><label class="label">Project name</label><input name="name" value="{{ $project->name }}" class="input" required></div>
        <div><label class="label">Department</label>
            <select name="department_id" class="input">@foreach($departments as $d)<option value="{{ $d->id }}" @selected($project->department_id===$d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div><label class="label">Description</label><input name="description" value="{{ $project->description }}" class="input"></div>
    </div>
    <div class="mt-4"><label class="label">Automatic file name <span class="font-normal text-slate-400">(optional — after indexing, e.g. {contract_number}-{invoice_number}; blank keeps the uploaded name)</span></label>
        <input name="naming_pattern" value="{{ $project->naming_pattern }}" class="input" placeholder="{contract_number}-{invoice_number}"></div>
    <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="ocr_enabled" value="1" class="rounded border-slate-300" @checked($project->ocr_enabled)> Read text inside documents on arrival (OCR) so it can be searched</label>
    <div class="mt-5 flex justify-end gap-2"><button class="btn btn-primary">Save settings</button></div>
</form>

{{-- Index fields --}}
<section class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Index fields</h2>
        <p class="text-xs text-slate-500">These are the values captured for every document in this project. They drive search and reports.</p></div>

    <div class="divide-y divide-slate-100">
    @forelse($project->fields as $f)
        <details class="group">
            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
                <span class="flex-1"><span class="font-medium">{{ $f->label }}</span>
                    <span class="ml-2 badge badge-slate">{{ $f->typeLabel() }}</span>
                    @if($f->required)<span class="badge badge-amber">Required</span>@endif
                    @if($f->read_only)<span class="badge badge-slate">Read-only</span>@endif
                    @unless($f->searchable)<span class="badge badge-slate">Not searchable</span>@endunless</span>
                <span class="flex items-center gap-1" onclick="event.stopPropagation()">
                    <form method="post" action="{{ route('fields.move', $f) }}" data-no-confirm>@csrf<input type="hidden" name="direction" value="up"><button class="icon-btn" title="Move up" @disabled($loop->first)><i data-lucide="chevron-up"></i></button></form>
                    <form method="post" action="{{ route('fields.move', $f) }}" data-no-confirm>@csrf<input type="hidden" name="direction" value="down"><button class="icon-btn" title="Move down" @disabled($loop->last)><i data-lucide="chevron-down"></i></button></form>
                    <span class="icon-btn" title="Edit"><i data-lucide="pencil"></i></span>
                    <form method="post" action="{{ route('fields.destroy', $f) }}" data-danger data-confirm-title="Hide \"{{ $f->label }}\"?" data-confirm="The field disappears from forms and search, but every stored value is kept. You can restore it later." data-confirm-button="Yes, hide">
                        @csrf @method('DELETE')<button class="icon-btn hover:!text-red-600" title="Hide"><i data-lucide="archive"></i></button></form>
                </span>
            </summary>
            <form method="post" action="{{ route('fields.update', $f) }}" class="grid gap-4 border-t border-slate-100 bg-slate-50/60 px-5 py-4 sm:grid-cols-2">
                @csrf @method('PUT')
                @include('structure._field-form', ['f' => $f])
                <div class="sm:col-span-2 flex justify-end"><button class="btn btn-primary btn-sm">Save field</button></div>
            </form>
        </details>
    @empty
        <p class="px-5 py-6 text-sm text-slate-400">No index fields yet. Add the first one below. Documents uploaded before fields exist are filed as indexed.</p>
    @endforelse
    </div>

    <form method="post" action="{{ route('fields.store', $project) }}" class="grid gap-4 border-t border-slate-200 bg-slate-50/60 px-5 py-5 sm:grid-cols-2">
        @csrf
        <h3 class="sm:col-span-2 text-sm font-semibold">Add an index field</h3>
        @include('structure._field-form', ['f' => null])
        <div class="sm:col-span-2 flex justify-end"><button class="btn btn-primary"><i data-lucide="plus"></i> Add field</button></div>
    </form>

    @if($hidden->isNotEmpty())
        <div class="border-t border-slate-100 px-5 py-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Hidden fields</p>
            @foreach($hidden as $h)
                <form method="post" action="{{ route('fields.restore', $h->id) }}" data-confirm-title="Restore \"{{ $h->label }}\"?" data-confirm="Its old values will reappear." data-confirm-button="Yes, restore" class="mb-1 flex items-center justify-between text-sm">
                    @csrf <span>{{ $h->label }} <span class="text-slate-400">· {{ $h->typeLabel() }}</span></span>
                    <button class="btn btn-ghost btn-sm"><i data-lucide="archive-restore"></i> Restore</button></form>
            @endforeach
        </div>
    @endif
</section>

{{-- Access --}}
<form method="post" action="{{ route('projects.access', $project) }}" class="card mb-6">
    @csrf @method('PUT')
    <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Who can use this project</h2>
        <p class="text-xs text-slate-500">Client Admins always have full access. Tick what each user may do here.</p></div>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-slate-50"><tr><th class="th">User</th>@foreach(\App\Models\ProjectRight::RIGHTS as $label)<th class="th text-center">{{ $label }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($users as $u)
                <tr><td class="td"><p class="font-medium">{{ $u->name }}</p><p class="text-xs text-slate-500">{{ $u->email }}</p></td>
                    @foreach(\App\Models\ProjectRight::RIGHTS as $col => $label)
                        <td class="td text-center"><input type="checkbox" name="rights[{{ $u->id }}][{{ $col }}]" value="1" class="size-4 rounded border-slate-300" @checked($rights[$u->id]->{$col} ?? false)></td>
                    @endforeach</tr>
            @empty
                <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-400">No users to assign yet. Add users with the "User" role first.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($users->isNotEmpty())<div class="flex justify-end border-t border-slate-100 px-5 py-3"><button class="btn btn-primary">Save access</button></div>@endif
</form>

<form method="post" action="{{ route('projects.destroy', $project) }}" data-danger data-confirm-title="Delete {{ $project->name }}?" data-confirm="Only projects without documents can be deleted." data-confirm-button="Yes, delete" class="flex justify-end">
    @csrf @method('DELETE')<button class="btn btn-ghost text-red-600"><i data-lucide="trash-2"></i> Delete project</button></form>
@endsection

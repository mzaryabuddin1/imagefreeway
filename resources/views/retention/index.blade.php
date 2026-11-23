@extends('layouts.app')
@section('title', 'Retention')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Retention &amp; legal hold</h1>
    <p class="text-sm text-slate-500">Set how long each project keeps documents, review what is due, and destroy with a certificate.</p></div>

<section class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Review queue <span class="ml-2 text-xs font-normal text-slate-500">{{ $due->count() }} due · {{ $upcoming }} more in the next 90 days</span></div>
    @if($due->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-slate-400">Nothing is due for disposal.</p>
    @else
    <form method="post" action="{{ route('retention.review') }}" id="review-form" data-confirm-title="Apply to the ticked documents?" data-confirm="Destroying permanently removes the file. A certificate is kept." data-confirm-button="Yes, continue" data-danger>
        @csrf
        <ul class="divide-y divide-slate-100">
            @foreach($due as $d)
                <li><label class="flex cursor-pointer items-center gap-3 px-5 py-2.5 text-sm hover:bg-slate-50"><input type="checkbox" name="ids[]" value="{{ $d->id }}" class="rounded border-slate-300">
                    <span class="flex-1 font-medium">{{ $d->original_name }} <span class="font-normal text-slate-400">· {{ $d->project?->name }}</span></span>
                    <span class="text-xs text-slate-500">due {{ $d->disposal_date->format('d M Y') }}</span></label></li>
            @endforeach
        </ul>
        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
            <input name="reason" class="input max-w-xs" placeholder="Reason (for hold)">
            <button name="do" value="destroy" class="btn btn-danger btn-sm">Destroy</button>
            <button name="do" value="defer" class="btn btn-ghost btn-sm">Defer 90 days</button>
            <button name="do" value="hold" class="btn btn-ghost btn-sm">Legal hold</button>
        </div>
    </form>
    @endif
</section>

<section class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3 font-semibold">Retention schedule per project</div>
    <div class="divide-y divide-slate-100">
    @forelse($projects as $p)
        <form method="post" action="{{ route('retention.project', $p) }}" data-no-confirm class="flex flex-wrap items-end gap-3 px-5 py-3">
            @csrf @method('PUT')
            <div class="min-w-48 flex-1 text-sm"><span class="font-medium">{{ $p->name }}</span><span class="block text-xs text-slate-400">{{ $p->department->name }}</span></div>
            <div><label class="label">Keep for (years)</label><input type="number" name="retention_years" value="{{ $p->retention_years }}" min="1" max="100" class="input w-28" placeholder="Forever"></div>
            <div><label class="label">Counted from</label>
                <select name="retention_field_id" class="input"><option value="">Upload date</option>@foreach($p->fields as $f)<option value="{{ $f->id }}" @selected($p->retention_field_id === $f->id)>{{ $f->label }}</option>@endforeach</select></div>
            <button class="btn btn-ghost btn-sm">Save</button>
        </form>
    @empty
        <p class="px-5 py-6 text-sm text-slate-400">No projects yet.</p>
    @endforelse
    </div>
</section>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card">
        <div class="border-b border-slate-100 px-5 py-3 font-semibold">Legal holds ({{ $held->count() }})</div>
        <ul class="divide-y divide-slate-100">
            @forelse($held as $d)
                <li class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm">
                    <a href="{{ route('documents.show', $d) }}" class="min-w-0"><span class="font-medium text-brand">{{ $d->original_name }}</span><span class="block truncate text-xs text-slate-500">{{ $d->legal_hold_reason }}</span></a>
                    <form method="post" action="{{ route('documents.release', $d) }}" data-confirm-title="Release the legal hold?" data-confirm-button="Yes, release">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Release</button></form>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-400">No documents are on hold.</li>
            @endforelse
        </ul>
    </section>
    <section class="card">
        <div class="border-b border-slate-100 px-5 py-3 font-semibold">Destruction certificates</div>
        <ul class="divide-y divide-slate-100">
            @forelse($certificates as $c)
                <li><a href="{{ route('retention.certificate', $c) }}" class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm hover:bg-slate-50"><span class="font-medium">{{ $c->document_name }}</span><span class="text-xs text-slate-500">{{ $c->destroyed_at->format('d M Y') }}</span></a></li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-400">No certificates yet.</li>
            @endforelse
        </ul>
    </section>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Document types')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Document types &amp; email-in</h1>
    <p class="text-sm text-slate-500">Each type can have its own email address. A document emailed to it is filed in the project, tagged with the type, the number in the subject becomes its index value, and its workflow starts.</p></div>

<form method="post" action="{{ route('doctypes.store') }}" data-no-confirm class="card card-pad mb-6 grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-6">
    @csrf
    <div class="lg:col-span-2"><label class="label req">Type name</label><input name="name" class="input" placeholder="e.g. Initial QB Receipt" required></div>
    <div><label class="label">Email address</label><div class="flex items-center"><input name="email_alias" class="input rounded-r-none" placeholder="wdg.receipts"><span class="rounded-r-lg border border-l-0 border-slate-200 bg-slate-50 px-2 py-2.5 text-xs text-slate-500">&#64;{{ $inboundDomain }}</span></div></div>
    <div><label class="label">File into project</label><select name="project_id" class="input"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
    <div><label class="label">Start workflow</label><select name="workflow_id" class="input"><option value="">None</option>@foreach($workflows as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
    <div><label class="label">Number goes into</label><input name="index_field" class="input" placeholder="contract_number"></div>
    <div class="lg:col-span-5"><label class="label">Subject pattern <span class="font-normal text-slate-400">(optional regular expression with one group; blank finds the first number such as CN-1234 or 20481)</span></label><input name="subject_pattern" class="input" placeholder="/Contract\s*#?\s*(\d+)/i"></div>
    <button class="btn btn-primary"><i data-lucide="plus"></i> Add</button>
</form>

<div class="space-y-3">
@forelse($types as $t)
    <details class="card">
        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-5 py-3">
            <span class="font-semibold">{{ $t->name }}</span>
            @if($t->email_alias)<span class="badge badge-blue">{{ $t->email_alias }}&#64;{{ $inboundDomain }}</span>@endif
            @if($t->workflow)<span class="badge badge-slate">→ {{ $t->workflow->name }}</span>@endif
            <span class="ml-auto text-xs text-slate-400">Edit</span>
        </summary>
        <form method="post" action="{{ route('doctypes.update', $t) }}" class="grid gap-4 border-t border-slate-100 bg-slate-50/60 p-5 sm:grid-cols-2 lg:grid-cols-4">
            @csrf @method('PUT')
            <div><label class="label req">Type name</label><input name="name" value="{{ $t->name }}" class="input bg-white" required></div>
            <div><label class="label">Email address (before &#64;{{ $inboundDomain }})</label><input name="email_alias" value="{{ $t->email_alias }}" class="input bg-white"></div>
            <div><label class="label">File into project</label><select name="project_id" class="input bg-white"><option value="">—</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected($t->project_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
            <div><label class="label">Start workflow</label><select name="workflow_id" class="input bg-white"><option value="">None</option>@foreach($workflows as $w)<option value="{{ $w->id }}" @selected($t->workflow_id === $w->id)>{{ $w->name }}</option>@endforeach</select></div>
            <div><label class="label">Number goes into</label><input name="index_field" value="{{ $t->index_field }}" class="input bg-white"></div>
            <div class="lg:col-span-3"><label class="label">Subject pattern</label><input name="subject_pattern" value="{{ $t->subject_pattern }}" class="input bg-white"></div>
            <div class="flex justify-end gap-2 sm:col-span-2 lg:col-span-4"><button class="btn btn-primary btn-sm">Save</button></div>
        </form>
        <form method="post" action="{{ route('doctypes.destroy', $t) }}" data-danger data-confirm-title="Delete {{ $t->name }}?" data-confirm="Documents keep their label; the email address stops working." data-confirm-button="Yes, delete" class="border-t border-slate-100 px-5 py-2 text-right">
            @csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Delete type</button></form>
    </details>
@empty
    <div class="card card-pad text-center text-sm text-slate-400">No document types yet.</div>
@endforelse
</div>
@endsection

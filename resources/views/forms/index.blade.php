@extends('layouts.app')
@section('title', 'eForms')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">eForms</h1>
    <p class="text-sm text-slate-500">Forms people fill in on screen: sign-off forms, checklists, requests. Attach one to a workflow task, or fill it in from any document.</p></div>

<form method="post" action="{{ route('forms.store') }}" data-no-confirm class="card card-pad mb-6 flex flex-wrap items-end gap-4">
    @csrf
    <div class="min-w-64 flex-1"><label class="label req">Form name</label><input name="name" class="input" placeholder="e.g. Installation sign-off" required></div>
    <button class="btn btn-primary"><i data-lucide="plus"></i> Create form</button>
</form>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">Form</th><th class="th">Questions</th><th class="th">Filled in</th><th class="th">Status</th><th class="th"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($forms as $f)
            <tr><td class="td font-semibold">{{ $f->name }}</td><td class="td">{{ count($f->fields ?? []) }}</td><td class="td">{{ $f->submissions_count }}</td>
                <td class="td"><span class="badge {{ $f->active ? 'badge-green' : 'badge-slate' }}">{{ $f->active ? 'On' : 'Off' }}</span></td>
                <td class="td text-right"><a href="{{ route('forms.edit', $f) }}" class="btn btn-ghost btn-sm"><i data-lucide="pencil"></i> Edit</a></td></tr>
        @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-400">No forms yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

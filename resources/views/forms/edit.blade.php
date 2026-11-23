@extends('layouts.app')
@section('title', $form->name)

@section('content')
<div class="mb-6"><a href="{{ route('forms') }}" class="text-sm text-brand hover:underline">← eForms</a>
    <h1 class="mt-1 text-2xl font-bold">{{ $form->name }}</h1></div>

<div data-form-builder data-payload="{{ json_encode($payload) }}" class="space-y-5">
    <div class="card card-pad grid gap-4 sm:grid-cols-[1fr_1fr_auto]">
        <div><label class="label req">Form name</label><input id="ff-name" class="input" value="{{ $form->name }}" maxlength="120"></div>
        <div><label class="label">Short description</label><input id="ff-desc" class="input" value="{{ $form->description }}" maxlength="300"></div>
        <label class="flex items-center gap-2 pt-6 text-sm"><input id="ff-active" type="checkbox" class="rounded border-slate-300" @checked($form->active)> Switched on</label>
    </div>
    <div class="card card-pad"><h2 class="mb-3 font-semibold">Questions</h2>
        <div id="ff-list" class="space-y-3"></div>
        <div class="mt-4 flex gap-2"><button type="button" id="ff-add" class="btn btn-ghost"><i data-lucide="plus"></i> Add question</button>
            <button type="button" id="ff-save" class="btn btn-primary"><i data-lucide="save"></i> Save form</button></div>
    </div>
</div>

<form method="post" action="{{ route('forms.destroy', $form) }}" data-danger data-confirm-title="Delete {{ $form->name }}?" data-confirm="Submitted answers are kept on their documents." data-confirm-button="Yes, delete" class="mt-4 flex justify-end">
    @csrf @method('DELETE')<button class="btn btn-ghost text-red-600"><i data-lucide="trash-2"></i> Delete form</button></form>
@endsection

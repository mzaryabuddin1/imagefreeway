@extends('layouts.app')
@section('title', 'Destruction certificate')

@section('content')
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('retention') }}" class="text-sm text-brand hover:underline">← Retention</a>
    <button type="button" onclick="window.print()" class="btn btn-primary"><i data-lucide="download"></i> Print / save as PDF</button>
</div>
<div class="card card-pad mx-auto max-w-2xl">
    <h1 class="text-center text-2xl font-bold">Certificate of Destruction</h1>
    <p class="mb-6 mt-1 text-center text-sm text-slate-500">Certificate #{{ $certificate->id }}</p>
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2"><dt class="text-slate-500">Document</dt><dd class="font-medium">{{ $certificate->document_name }}</dd></div>
        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2"><dt class="text-slate-500">Project</dt><dd>{{ $certificate->project_name }}</dd></div>
        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2"><dt class="text-slate-500">Scheduled disposal date</dt><dd>{{ $certificate->disposal_date?->format('d M Y') }}</dd></div>
        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2"><dt class="text-slate-500">Destroyed on</dt><dd>{{ $certificate->destroyed_at->format('d M Y H:i') }}</dd></div>
        <div class="flex justify-between gap-4 border-b border-slate-100 pb-2"><dt class="text-slate-500">Approved by</dt><dd>{{ $certificate->approver?->name ?? '—' }}</dd></div>
        @foreach(($certificate->index_values ?? []) as $k => $v)
            <div class="flex justify-between gap-4 border-b border-slate-100 pb-2"><dt class="text-slate-500">{{ str($k)->replace('_', ' ')->title() }}</dt><dd>{{ is_array($v) ? implode(', ', $v) : $v }}</dd></div>
        @endforeach
    </dl>
    <p class="mt-6 text-xs text-slate-400">The file and its index record were permanently removed under the project's retention schedule. This certificate is the only remaining record.</p>
</div>
@endsection

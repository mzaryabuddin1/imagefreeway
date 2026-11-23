@extends('layouts.app')
@section('title', 'Signature certificate')

@section('content')
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('documents.show', $document) }}" class="text-sm text-brand hover:underline">← {{ $document->original_name }}</a>
    <button type="button" onclick="window.print()" class="btn btn-primary"><i data-lucide="download"></i> Print / save as PDF</button>
</div>
<div class="card card-pad mx-auto max-w-2xl">
    <h1 class="text-center text-2xl font-bold">Certificate of Electronic Signature</h1>
    <p class="mb-6 mt-1 text-center text-sm text-slate-500">Reference #{{ $sig->id }}</p>
    <dl class="space-y-3 text-sm">
        @foreach([
            'Document' => $document->original_name, 'Signed by' => $sig->signer_name.' ('.$sig->signer_email.')', 'Name typed' => $sig->typed_name,
            'Signed on' => $sig->signed_at->format('d M Y H:i:s').' UTC', 'From IP address' => $sig->ip, 'Requested by' => $sig->requester?->name ?? '—',
            'Document fingerprint (SHA-256)' => $sig->doc_hash ?? 'not available',
        ] as $k => $v)
            <div class="flex justify-between gap-4 border-b border-slate-100 pb-2"><dt class="text-slate-500">{{ $k }}</dt><dd class="break-all text-right font-medium">{{ $v }}</dd></div>
        @endforeach
    </dl>
    <div class="mt-6 text-center"><img src="{{ $sig->signature }}" alt="Signature" class="mx-auto max-h-28"><p class="mt-1 text-xs text-slate-400">Signature as drawn</p></div>
    <p class="mt-6 text-xs text-slate-400">The fingerprint identifies the exact file that was shown to the signer. If the file changes, its fingerprint will not match this certificate.</p>
</div>
@endsection

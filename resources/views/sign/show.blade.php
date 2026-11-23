@extends('layouts.guest')
@section('title', 'Sign document')

@section('content')
<div class="min-h-screen bg-slate-50">
    <header class="border-b border-slate-200 bg-white"><div class="mx-auto flex h-16 max-w-5xl items-center px-4"><img src="{{ $brand->logoUrl }}" alt="{{ $brand->name }}" class="h-9 w-auto"></div></header>
    <main class="mx-auto grid max-w-5xl gap-5 px-4 py-8 lg:grid-cols-[1fr_22rem]">
        <section class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-3"><h1 class="font-bold">{{ $doc->original_name }}</h1><p class="text-xs text-slate-500">Review the document, then sign on the right{{ $req->role ? ' (you are signing as '.$req->role.')' : '' }}.</p></div>
            @if($req->status === 'cancelled')
                <p class="p-10 text-center text-sm text-slate-500">This request was cancelled by the sender.</p>
            @elseif($pageCount > 0)
                <div class="bg-slate-100 p-4" id="sign-pages">
                    @if($open)<p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"><i data-lucide="pen-line" class="mr-1 inline"></i> Your signature goes in the {{ $boxes->count() === 1 ? 'highlighted box' : $boxes->count().' highlighted boxes' }}. Draw it on the right and it appears there.</p>@endif
                    @for($p = 1; $p <= $pageCount; $p++)
                        <div class="mk-stage mb-4"><img src="{{ route('sign.page', [$token, $p]) }}" alt="Page {{ $p }}" draggable="false" loading="lazy">
                            <div class="mk-layer" style="cursor:default">
                                @foreach($boxes->where('page', $p) as $b)
                                    <div class="mk-mark mk-sign" data-sigbox style="left:{{ $b->x * 100 }}%;top:{{ $b->y * 100 }}%;width:{{ $b->w * 100 }}%;height:{{ $b->h * 100 }}%"><span class="mk-tag">Sign here</span></div>
                                @endforeach
                            </div></div>
                    @endfor
                </div>
            @elseif($doc->isPdf())
                <iframe src="{{ route('sign.file', $token) }}" title="{{ $doc->original_name }}" class="h-[75vh] w-full bg-slate-100"></iframe>
            @elseif($doc->isImage())
                <div class="flex justify-center bg-slate-100 p-4"><img src="{{ route('sign.file', $token) }}" alt="" class="h-auto max-w-full"></div>
            @else
                <div class="p-10 text-center text-sm text-slate-500">This file cannot be previewed here. <a href="{{ route('sign.file', $token) }}" data-no-ajax class="text-brand underline">Open it</a> in a new tab to read it.</div>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="card card-pad text-sm">
                <p class="text-slate-500">For</p><p class="font-semibold">{{ $req->signer_name }}@if($req->role) <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $req->role }}</span>@endif</p><p class="text-xs text-slate-400">{{ $req->signer_email }}</p>
                @if($req->message)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-slate-600">{{ $req->message }}</p>@endif
            </div>

            @if($others->count() > 1)
                <div class="card card-pad text-sm"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Who signs</p>
                    <ul class="space-y-1.5">@foreach($others as $o)
                        <li class="flex items-center justify-between gap-2 text-xs"><span class="truncate {{ $o->id === $req->id ? 'font-semibold text-slate-800' : 'text-slate-600' }}">{{ $o->seq ? $o->seq.'. ' : '' }}{{ $o->signer_name }}{{ $o->id === $req->id ? ' (you)' : '' }}</span>
                            <span class="badge {{ $o->status === 'signed' ? 'badge-green' : ($o->status === 'declined' ? 'badge-amber' : ($o->status === 'queued' ? 'badge-slate' : 'badge-blue')) }}">{{ $o->status === 'signed' ? 'Signed' : ($o->status === 'declined' ? 'Declined' : ($o->status === 'queued' ? 'Waiting' : 'Signing')) }}</span></li>
                    @endforeach</ul></div>
            @endif

            @if($open)
                <form method="post" action="{{ route('sign.submit', $token) }}" data-no-confirm data-inline class="card card-pad space-y-4">
                    @csrf
                    <div><label class="label req">Type your full name</label><input name="typed_name" class="input" value="{{ $req->signer_name }}" required></div>
                    <div data-sigmethods>
                        <label class="label req">Your signature</label>
                        <input type="hidden" name="signature" data-sig-value><input type="hidden" name="method" value="draw" data-sig-method>
                        <div class="mb-2 grid grid-cols-4 gap-1 text-xs">
                            <button type="button" data-sm="draw" class="sm-tab is-on"><i data-lucide="pen-line"></i> Draw</button>
                            <button type="button" data-sm="type" class="sm-tab"><i data-lucide="type"></i> Type</button>
                            <button type="button" data-sm="upload" class="sm-tab"><i data-lucide="upload"></i> Upload</button>
                            <button type="button" data-sm="camera" class="sm-tab"><i data-lucide="camera"></i> Camera</button>
                        </div>
                        <div data-pane="draw"><div data-sigpad class="sigpad"><canvas></canvas><input type="hidden"><button type="button" data-sig-clear class="mt-1 text-xs text-slate-500 hover:underline">Clear</button></div></div>
                        <div data-pane="type" class="hidden"><input class="input" data-type-input placeholder="Type your name" maxlength="60"><canvas data-type-preview width="600" height="200" class="sigpad mt-2 w-full" style="height:7rem"></canvas></div>
                        <div data-pane="upload" class="hidden"><input type="file" accept="image/*" data-upload-input class="input"><p class="mt-1 text-[11px] text-slate-500">A clear photo or scan of your signature on white paper works best.</p><img data-upload-preview alt="" class="mt-2 hidden max-h-28 rounded border border-slate-200 bg-white p-1"></div>
                        <div data-pane="camera" class="hidden"><video data-cam-video autoplay playsinline muted class="hidden w-full rounded-lg bg-black"></video><img data-cam-preview alt="" class="mt-2 hidden max-h-28 rounded border border-slate-200 bg-white p-1">
                            <div class="mt-2 flex gap-2"><button type="button" data-cam-start class="btn btn-ghost btn-sm"><i data-lucide="camera"></i> Start camera</button><button type="button" data-cam-snap class="btn btn-primary btn-sm hidden">Capture</button></div>
                            <p data-cam-msg class="mt-1 text-[11px] text-slate-500">Sign on white paper and hold it to the camera.</p></div>
                    </div>
                    <label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-slate-300"> I agree that my electronic signature is as valid as a handwritten one, and that I have read this document.</label>
                    <button class="btn btn-primary w-full">Sign document</button>
                </form>
                <form method="post" action="{{ route('sign.decline', $token) }}" data-confirm-title="Decline to sign?" data-confirm="The sender will be told." data-confirm-button="Yes, decline" data-danger class="card card-pad space-y-2">
                    @csrf
                    <label class="label">Reason (optional)</label><input name="reason" class="input" maxlength="300">
                    <button class="btn btn-ghost w-full">Decline</button>
                </form>
            @else
                <div class="card card-pad text-center">
                    @if($req->status === 'signed')
                        <span class="badge badge-green">Signed</span>
                        <p class="mt-3 text-sm font-medium">Thank you, {{ $req->typed_name }}.</p>
                        <p class="text-xs text-slate-500">Signed {{ $req->signed_at->format('d M Y H:i') }}</p>
                        <img src="{{ $req->makeVisible('signature')->signature }}" alt="Your signature" class="mx-auto mt-3 max-h-24">
                        <p class="mt-3 break-all text-[10px] text-slate-400">Document fingerprint (SHA-256): {{ $req->doc_hash }}</p>
                    @elseif($req->status === 'declined')
                        <span class="badge badge-amber">Declined</span><p class="mt-3 text-sm text-slate-500">You declined to sign this document.</p>
                    @elseif($req->status === 'queued')
                        <span class="badge badge-slate">Not your turn yet</span><p class="mt-3 text-sm text-slate-500">Someone else needs to sign first. You will get an email with this same link as soon as it is your turn.</p>
                    @else
                        <span class="badge badge-slate">{{ $req->statusLabel() }}</span><p class="mt-3 text-sm text-slate-500">This request is no longer open. Ask the sender for a new one.</p>
                    @endif
                </div>
            @endif
        </aside>
    </main>
    <p class="pb-8 text-center text-xs text-slate-400">{{ $brand->footer }}</p>
</div>
@endsection

@extends('layouts.guest')
@section('title', 'Shared document')

@section('content')
<div class="min-h-screen bg-slate-50">
    <header class="border-b border-slate-200 bg-white"><div class="mx-auto flex h-16 max-w-5xl items-center px-4"><img src="{{ $brand->logoUrl }}" alt="{{ $brand->name }}" class="h-9 w-auto"></div></header>
    <main class="mx-auto max-w-5xl px-4 py-8">
        @if(! $unlocked)
            <div class="card card-pad mx-auto max-w-md">
                <h1 class="text-lg font-bold">This document is protected</h1>
                <p class="mb-4 mt-1 text-sm text-slate-500">Enter the access code you were given.</p>
                <form method="post" action="{{ route('share.unlock', $token) }}" data-no-confirm data-inline class="space-y-4">
                    @csrf
                    <div><label class="label req">Access code</label><input name="code" class="input @error('code') border-red-400 @enderror" autocomplete="off" autofocus required>
                        @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <button class="btn btn-primary w-full">Open document</button>
                </form>
            </div>
        @else
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div><h1 class="text-xl font-bold">{{ $doc->original_name }}</h1><p class="text-xs text-slate-500">Link valid until {{ $link->expires_at->format('d M Y') }}</p></div>
                @if($link->allow_download)<a href="{{ route('share.download', $token) }}" data-no-ajax class="btn btn-primary"><i data-lucide="download"></i> Download</a>@endif
            </div>
            <div class="card overflow-hidden">
                @if($doc->isPdf())
                    <iframe src="{{ route('share.file', $token) }}" title="{{ $doc->original_name }}" class="h-[78vh] w-full bg-slate-100"></iframe>
                @elseif($doc->isImage())
                    <div class="flex justify-center bg-slate-100 p-4"><img src="{{ route('share.file', $token) }}" alt="{{ $doc->original_name }}" class="h-auto max-w-full"></div>
                @else
                    <div class="p-10 text-center text-sm text-slate-500">This file type cannot be previewed.@if($link->allow_download) Use Download to open it.@endif</div>
                @endif
            </div>
        @endif
        <p class="mt-8 text-center text-xs text-slate-400">{{ $brand->footer }}</p>
    </main>
</div>
@endsection

@extends('layouts.app')
@section('title', 'How to Use')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">How to Use</h1>
    <p class="text-sm text-slate-500">Step-by-step guides. This page is updated every time a feature is added or changed.</p></div>

<div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
    <nav class="card card-pad h-fit text-sm lg:sticky lg:top-24">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">On this page</p>
        @foreach($sections as $section => $entries)
            <p class="mt-3 font-semibold text-slate-700">{{ $section }}</p>
            @foreach($entries as $e)<a href="#{{ $e->slug }}" data-no-ajax class="block py-1 text-slate-500 hover:text-brand">{{ $e->title }}</a>@endforeach
        @endforeach
    </nav>

    <div class="space-y-6">
        @foreach($sections as $section => $entries)
            @foreach($entries as $e)
                <article id="{{ $e->slug }}" class="card card-pad scroll-mt-24">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ $section }}</p><h2 class="text-lg font-bold">{{ $e->title }}</h2></div>
                        <span class="shrink-0 text-xs text-slate-400">Updated {{ $e->updated_at->format('d M Y') }}</span>
                    </div>
                    <div class="space-y-2 text-sm leading-6 text-slate-700 [&_li]:ml-5 [&_li]:list-disc [&_ol>li]:list-decimal [&_strong]:font-semibold [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1">{!! $e->body !!}</div>
                </article>
            @endforeach
        @endforeach
    </div>
</div>
@endsection

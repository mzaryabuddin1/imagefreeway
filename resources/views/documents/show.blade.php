@extends('layouts.app')
@section('title', $document->original_name)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0"><a href="{{ route('projects.show', $project) }}" class="text-sm text-brand hover:underline">← {{ $project->name }}</a>
        <h1 class="truncate text-xl font-bold">{{ $document->original_name }}</h1></div>
    <div class="flex flex-wrap items-center gap-2">
        @if($prev)<a href="{{ route('documents.show', $prev->id) }}" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left"></i> Previous</a>@endif
        @if($next)<a href="{{ route('documents.show', $next->id) }}" class="btn btn-ghost btn-sm">Next <i data-lucide="arrow-right"></i></a>@endif
        @if($canDownload)<a href="{{ route('documents.download', $document) }}" data-no-ajax class="btn btn-ghost btn-sm"><i data-lucide="download"></i> Download</a>@endif
        @if($canDelete)
            <form method="post" action="{{ route('documents.destroy', $document) }}" data-danger data-confirm-title="Delete this document?" data-confirm="It moves to the recycle bin, where it can be restored." data-confirm-button="Yes, delete">
                @csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-red-600"><i data-lucide="trash-2"></i> Delete</button></form>
        @endif
    </div>
</div>

<div class="grid gap-5 xl:grid-cols-[22rem_1fr]">
    <aside class="space-y-5">
        <form method="post" action="{{ route('documents.update', $document) }}" class="card card-pad">
            @csrf @method('PUT')
            <div class="mb-4 flex items-center justify-between"><h2 class="font-semibold">Index values</h2>
                @if($project->fields->isEmpty())
                    <span class="badge badge-slate" title="This project has no index fields, so there is nothing to fill in.">Not needed</span>
                @else
                    <span class="badge {{ $document->status === 'indexed' ? 'badge-green' : 'badge-amber' }}">{{ $document->status === 'indexed' ? 'Filled in' : 'Waiting to be filled' }}</span>
                @endif</div>

            @forelse($project->fields as $f)
                @php($val = old("values.{$f->key}", $document->index_values[$f->key] ?? ''))
                @php($locked = ! $canIndex || $f->type === 'calculated' || ($f->read_only && ! auth()->user()->isAdmin()))
                <div class="mb-4">
                    <label class="label">{{ $f->label }}</label>
                    @if($f->type === 'yesno')
                        <select name="values[{{ $f->key }}]" class="input" @disabled($locked) @required($f->required)><option value="">—</option>@foreach(['Yes','No'] as $o)<option @selected($val===$o)>{{ $o }}</option>@endforeach</select>
                    @elseif($f->type === 'picklist')
                        <select name="values[{{ $f->key }}]" class="input" @disabled($locked) @required($f->required)><option value="">—</option>@foreach($f->options ?? [] as $o)<option @selected($val===$o)>{{ $o }}</option>@endforeach</select>
                    @else
                        <input name="values[{{ $f->key }}]" value="{{ $val }}" class="input"
                            type="{{ ['number' => 'number', 'amount' => 'number', 'date' => 'date'][$f->type] ?? 'text' }}" @if($f->type==='amount') step="0.01" @elseif($f->type==='number') step="1" @endif @disabled($locked) @required($f->required)>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500">This project has no index fields, so there is nothing to fill in here. The document is still searchable by its name and its text (see <em>Text search</em> below).@if(auth()->user()->isClientAdmin()) <a href="{{ route('projects.settings', $project) }}" class="text-brand hover:underline">Add some</a>.@endif</p>
            @endforelse

            @if($canIndex && $project->fields->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-primary" name="then" value="stay">Save</button>
                    @if($document->status === 'unindexed' || $remaining > 0)<button class="btn btn-ghost" name="then" value="next">Save &amp; next unindexed</button>@endif
                </div>
            @endif
        </form>

        <div class="card card-pad text-sm">
            <h2 class="mb-3 font-semibold">Details</h2>
            <dl class="space-y-2 text-slate-600">
                <div class="flex justify-between gap-3"><dt>Size</dt><dd>{{ $document->humanSize() }}</dd></div>
                <div class="flex justify-between gap-3"><dt>Added</dt><dd>{{ $document->created_at->format('d M Y H:i') }}</dd></div>
                <div class="flex justify-between gap-3"><dt>Added by</dt><dd>{{ $document->uploader?->name ?? '—' }}</dd></div>
                @if($document->indexed_at)<div class="flex justify-between gap-3"><dt>Indexed</dt><dd>{{ $document->indexed_at->format('d M Y H:i') }}</dd></div>@endif
                <div class="flex justify-between gap-3"><dt>Text search</dt><dd>@switch($document->ocr_status)
                    @case('done') <span class="text-emerald-600">Ready</span> @break
                    @case('unavailable') <span class="text-amber-600" title="The OCR engine is not installed on the server">Engine not installed</span> @break
                    @case('failed') <span class="text-red-600">Failed</span> @break
                    @default <span>—</span>
                @endswitch</dd></div>
            </dl>
        </div>
        @include('documents._panels')
    </aside>

    <section class="card overflow-hidden" id="markup-root" data-id="{{ $document->id }}">
        @if($document->isPdf() || $document->isImage())
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-2">
                <span class="text-xs text-slate-500">Add notes, highlights or redactions on the pages.</span>
                <button type="button" id="mk-open" class="btn btn-ghost btn-sm"><i data-lucide="highlighter"></i> Mark up</button>
            </div>
        @endif
        <div id="markup-panel" class="hidden">
            <div class="mk-bar">
                <button type="button" data-tool="note" class="btn btn-ghost btn-sm mk-tool"><i data-lucide="message-square"></i> Note</button>
                <button type="button" data-tool="highlight" class="btn btn-ghost btn-sm mk-tool"><i data-lucide="highlighter"></i> Highlight</button>
                <button type="button" data-tool="sign" class="btn btn-ghost btn-sm mk-tool"><i data-lucide="pen-line"></i> Sign here</button>
                <button type="button" data-tool="redact" class="btn btn-ghost btn-sm mk-tool"><i data-lucide="eraser"></i> Redact</button>
                <span class="mx-2 h-5 w-px bg-slate-200"></span>
                <button type="button" id="mk-prev" class="btn btn-ghost btn-sm"><i data-lucide="chevron-left"></i></button>
                <span id="mk-page" class="text-xs text-slate-600"></span>
                <button type="button" id="mk-next" class="btn btn-ghost btn-sm"><i data-lucide="chevron-right"></i></button>
                <button type="button" id="mk-close" class="btn btn-ghost btn-sm ml-auto">Close mark-up</button>
            </div>
            <p id="mk-hint" class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-xs text-slate-500"></p>
            <div id="mk-apply" class="mk-apply hidden items-center justify-between gap-2 border-b border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700">
                <span id="mk-redact-count"></span>
                <form method="post" action="{{ route('documents.redact', $document) }}" data-danger data-confirm-title="Create a redacted copy?" data-confirm="A new document is made with the marked areas blacked out for good. The original stays as it is." data-confirm-button="Yes, create copy">
                    @csrf<button class="btn btn-sm bg-red-600 text-white hover:bg-red-700">Create redacted copy</button>
                </form>
            </div>
            <div class="mk-scroll"><div class="mk-stage" id="mk-stage"><img id="mk-img" alt="Page" draggable="false"><div id="mk-layer" class="mk-layer"></div></div></div>
        </div>
        <div id="plain-viewer">
        @if($document->isPdf())
            <iframe src="{{ route('documents.file', $document) }}" title="{{ $document->original_name }}" class="h-[78vh] w-full bg-slate-100"></iframe>
        @elseif($document->isImage())
            <div class="flex max-h-[78vh] justify-center overflow-auto bg-slate-100 p-4"><img src="{{ route('documents.file', $document) }}" alt="{{ $document->original_name }}" class="h-auto max-w-full"></div>
        @else
            <div class="flex h-72 flex-col items-center justify-center gap-3 p-6 text-center text-slate-500">
                <i data-lucide="file-text" class="!size-10"></i>
                <p class="text-sm">This file type cannot be previewed in the browser.</p>
                @if($canDownload)<a href="{{ route('documents.download', $document) }}" data-no-ajax class="btn btn-primary"><i data-lucide="download"></i> Download to open</a>@endif
            </div>
        @endif
        </div>
    </section>
</div>
@endsection

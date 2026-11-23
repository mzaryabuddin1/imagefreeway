@php($me = auth()->user())
@php($formsById = $forms->keyBy('id'))

{{-- Workflow --}}
@if($items->isNotEmpty() || $startable->isNotEmpty())
<div id="workflow-card" class="card card-pad text-sm">
    <h2 class="mb-3 font-semibold">Workflow</h2>

    @foreach($items as $item)
        @php($node = $item->node())
        <div class="mb-4 rounded-lg border border-slate-200 p-3 last:mb-0">
            <div class="flex items-center justify-between gap-2">
                <span class="font-medium">{{ $item->workflow->name }}</span>
                @if($item->status === 'complete')<span class="badge badge-green">{{ $item->result ?: 'Complete' }}</span>
                @elseif($item->isOverdue())<span class="badge badge-amber">Overdue</span>
                @else<span class="badge badge-blue">In progress</span>@endif
            </div>
            @if($node)<p class="mt-1 text-slate-600">At: <strong>{{ $node['name'] }}</strong></p>
                @if($node['type'] === 'signature')<p class="mt-1 text-xs text-slate-500">Waiting for the signer.</p>@endif
                @if(! empty($node['config']['instructions']))<p class="mt-2 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">{{ $node['config']['instructions'] }}</p>@endif
            @endif

            @if($item->status === 'active' && $node && $node['type'] === 'task' && $item->isAssignedTo($me))
                @php($reqs = collect($node['config']['requirements'] ?? []))
                @php($formOpts = collect($node['config']['options'])->filter(fn ($o) => $reqs->contains(fn ($r) => $r['kind'] === 'form' && (! $r['on'] || in_array($o, $r['on'], true)))))
                <div class="mt-3 space-y-3 border-t border-slate-100 pt-3">
                    @if($formOpts->count() < count($node['config']['options']))
                    <form method="post" action="{{ route('workflow.act', $item) }}" data-confirm-title="Send this on?" data-confirm-button="Yes, continue" class="space-y-2">
                        @csrf
                        <input name="comment" class="input" placeholder="Note (optional)">
                        <div class="flex flex-wrap gap-2">
                            @foreach($node['config']['options'] as $o)@continue($formOpts->contains($o))
                                <button class="btn {{ $loop->first ? 'btn-primary' : 'btn-ghost' }} btn-sm" name="outcome" value="{{ $o }}">{{ $o }}</button>
                            @endforeach
                        </div>
                        <label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="then" value="next" class="rounded border-slate-300"> then open my next item</label>
                    </form>
                    @endif

                    @foreach($formOpts as $o)
                        <details class="rounded-lg border border-slate-200">
                            <summary class="cursor-pointer px-3 py-2 font-medium">{{ $o }} <span class="text-xs font-normal text-slate-400">· needs a form</span></summary>
                            <form method="post" action="{{ route('workflow.act', $item) }}" data-confirm-title="{{ $o }}?" data-confirm-button="Yes, submit" class="space-y-3 border-t border-slate-100 p-3">
                                @csrf
                                @foreach($reqs->where('kind', 'form')->filter(fn ($r) => ! $r['on'] || in_array($o, $r['on'], true)) as $r)
                                    @if($f = $formsById->get((int) $r['value']))
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $f->name }}</p>
                                        @include('forms._fields', ['form' => $f])
                                    @endif
                                @endforeach
                                <input name="comment" class="input" placeholder="Note (optional)">
                                <button class="btn btn-primary btn-sm" name="outcome" value="{{ $o }}">{{ $o }}</button>
                            </form>
                        </details>
                    @endforeach
                </div>
            @endif

            <details class="mt-3 text-xs text-slate-500" open><summary class="cursor-pointer font-medium">History</summary>
                <ol class="relative mt-3 ml-3 space-y-4 border-l-2 border-slate-200 pl-6">
                    @foreach($item->events->whereNotIn('action', ['entered', 'checked', 'routed', 'set']) as $e)
                        @php([$ic, $tone] = \App\Support\EventIcon::for($e))
                        <li class="relative">
                            <span class="absolute -left-[2.15rem] top-0 flex size-6 items-center justify-center rounded-full ring-4 ring-white {{ $tone }}"><i data-lucide="{{ $ic }}" class="!size-3.5"></i></span>
                            <p class="text-[13px] font-semibold leading-5 text-slate-800">{{ ucfirst(str_replace('_', ' ', $e->action)) }}@if($e->outcome): <span class="text-brand">{{ $e->outcome }}</span>@endif</p>
                            @if($e->node_name)<p class="text-slate-600">{{ $e->node_name }}</p>@endif
                            @if($e->comment)<p class="mt-0.5 rounded-md bg-slate-50 px-2 py-1 italic text-slate-600">“{{ $e->comment }}”</p>@endif
                            <p class="mt-0.5 text-[11px] text-slate-400">{{ $e->created_at->format('d M Y, H:i') }}@if($e->user) · {{ $e->user->name }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            </details>
        </div>
    @endforeach

    @if($startable->isNotEmpty())
        <form method="post" action="{{ route('workflow.start', $document) }}" data-confirm-title="Start this workflow?" data-confirm-button="Yes, start" class="flex gap-2 {{ $items->isNotEmpty() ? 'mt-3 border-t border-slate-100 pt-3' : '' }}">
            @csrf
            <select name="workflow_id" class="input">@foreach($startable as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
            <button class="btn btn-ghost">Start</button>
        </form>
    @endif
</div>
@endif

{{-- Document type and related documents --}}
<div class="card card-pad text-sm">
    <h2 class="mb-3 font-semibold">Document type</h2>
    @if($canIndex)
        <form method="post" action="{{ route('documents.type', $document) }}" class="flex gap-2">
            @csrf @method('PUT')
            <select name="doc_type" class="input"><option value="">— none —</option>@foreach($docTypes as $t)<option @selected($document->doc_type === $t)>{{ $t }}</option>@endforeach</select>
            <button class="btn btn-ghost">Save</button>
        </form>
    @else
        <p class="text-slate-600">{{ $document->doc_type ?? '—' }}</p>
    @endif
    @if($document->email_from)<p class="mt-2 text-xs text-slate-500">Emailed in by {{ $document->email_from }}</p>@endif
    @if($related->isNotEmpty())
        <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Same {{ $project->fields->firstWhere('key', $project->link_field)?->label ?? 'job' }}</h3>
        <ul class="space-y-1">@foreach($related as $r)<li><a href="{{ route('documents.show', $r) }}" class="text-brand hover:underline">{{ $r->original_name }}</a> <span class="text-xs text-slate-400">{{ $r->doc_type }}</span></li>@endforeach</ul>
    @endif
</div>

{{-- eForms --}}
@if($forms->isNotEmpty() || $submissions->isNotEmpty())
<div class="card card-pad text-sm">
    <h2 class="mb-3 font-semibold">Forms</h2>
    @foreach($submissions as $s)
        <details class="mb-2 rounded-lg border border-slate-200">
            <summary class="cursor-pointer px-3 py-2"><span class="font-medium">{{ $s->form->name }}</span> <span class="text-xs text-slate-400">· {{ $s->user?->name }} · {{ $s->created_at->format('d M H:i') }}</span></summary>
            <dl class="space-y-1 border-t border-slate-100 p-3 text-xs">
                @foreach($s->form->fields ?? [] as $f)
                    <div><dt class="text-slate-500">{{ $f['label'] }}</dt>
                        <dd class="font-medium">@if($f['type'] === 'signature' && ! empty($s->data[$f['key']]))<img src="{{ $s->data[$f['key']] }}" alt="Signature" class="max-h-16">
                            @elseif($f['type'] === 'checkbox'){{ ($s->data[$f['key']] ?? 0) ? 'Yes' : 'No' }}@else{{ $s->data[$f['key']] ?? '—' }}@endif</dd></div>
                @endforeach
            </dl>
        </details>
    @endforeach
    @if($canIndex && $forms->isNotEmpty())
        @foreach($forms as $f)
            <details class="mb-2 rounded-lg border border-dashed border-slate-300">
                <summary class="cursor-pointer px-3 py-2 text-slate-600">Fill in: {{ $f->name }}</summary>
                <form method="post" action="{{ route('forms.submit', [$document, $f]) }}" data-confirm-title="Save {{ $f->name }}?" class="border-t border-slate-100 p-3">
                    @csrf @include('forms._fields', ['form' => $f])<button class="btn btn-primary btn-sm">Save form</button>
                </form>
            </details>
        @endforeach
    @endif
</div>
@endif

{{-- eSignature --}}
@if($canDownload)
<div class="card card-pad text-sm" id="esign-card" data-esign>
    <h2 class="mb-1 font-semibold">eSignature</h2>
    <p class="mb-3 text-xs text-slate-500">Get this document signed by one person, several people in order, or send it to many people at once. Signers need no account: they get a link.</p>

    <div class="mb-3 rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
        <i data-lucide="pen-line" class="mr-1 inline"></i> <b>Step 1:</b> press <b>Mark up → Sign here</b> and draw a box for each signature. Give each box a <b>role</b> (Candidate, HR Manager…).
        <div class="mt-1.5 flex flex-wrap gap-1.5" id="sign-role-chips">
            @forelse($signRoles as $role => $n)
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold text-white" data-role-chip="{{ $role }}">{{ $role }} · {{ $n }} {{ Str::plural('box', $n) }}</span>
            @empty
                <span data-sign-count class="font-medium"></span>
            @endforelse
        </div>
    </div>

    <form method="post" action="{{ route('signatures.store', $document) }}" data-confirm-title="Send for signature?" data-confirm="Signers will be emailed (the first one only, if you chose signing in order)." data-confirm-button="Yes, send" class="space-y-3">
        @csrf
        <p class="text-xs font-semibold text-slate-700">Step 2: who signs?</p>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
            <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="one" checked data-esign-mode> One document, for the people below</label>
            <label class="flex items-center gap-1.5" id="bulk-toggle"><input type="radio" name="mode" value="many" data-esign-mode> A separate copy for many people (like 10 candidates)</label>
        </div>

        <div data-esign-many class="hidden space-y-2 rounded-lg border border-slate-200 p-3">
            <div><label class="label">Their role</label><input name="recipient_role" class="input" list="esign-roles" placeholder="e.g. Candidate" maxlength="60"></div>
            <div><label class="label">People: one per line, “Name, email”</label><textarea name="recipients" rows="4" class="input" placeholder="Asha Khan, asha@example.com&#10;Bilal Ahmed, bilal@example.com"></textarea></div>
            <p class="text-[11px] text-slate-500">Each person gets their own copy of this document. They sign first; then the signers listed below sign in order.</p>
        </div>

        <div>
            <p class="mb-1 text-xs text-slate-500" data-esign-one-label>Signers, in the order they sign:</p>
            <p class="mb-1 hidden text-xs text-slate-500" data-esign-many-label>Other signers after the recipient (optional):</p>
            <div id="signer-rows" class="space-y-2" data-roles='@json($signRoles->keys())'>
                <div class="rounded-lg border border-slate-200 p-2.5" data-signer-row>
                    @include('documents._signer_row', ['i' => 0, 'team' => $team, 'signRoles' => $signRoles])
                </div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm mt-2" data-add-signer><i data-lucide="plus"></i> Add another signer</button>
            <template id="signer-template"><div class="rounded-lg border border-slate-200 p-2.5" data-signer-row>@include('documents._signer_row', ['i' => '__i__', 'team' => $team, 'signRoles' => $signRoles])</div></template>
            <datalist id="esign-roles">@foreach($signRoles->keys() as $r)<option value="{{ $r }}">@endforeach</datalist>
        </div>

        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="in_order" value="1" checked> Sign in this order (each person is emailed only after the one before has signed)</label>
        <div class="grid grid-cols-[1fr_1fr] gap-2">
            <div><label class="label">Valid for (days)</label><input type="number" name="days" value="14" min="1" max="60" class="input" required></div>
            <div><label class="label">Remind every (days)</label><input type="number" name="remind_days" value="2" min="1" max="30" class="input"></div>
        </div>
        <div><label class="label">Message</label><input name="message" class="input" maxlength="500" placeholder="Optional note for the signers"></div>
        <button class="btn btn-primary btn-sm"><i data-lucide="send"></i> Send for signature</button>
    </form>

    @if($signatures->isNotEmpty())
        <h3 class="mt-5 border-t border-slate-100 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Signing progress</h3>
        <ul class="mt-2 space-y-2" id="sign-progress">
            @foreach($signatures as $s)
                <li class="rounded-lg border border-slate-100 p-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800">@if($s->seq){{ $s->seq }}. @endif{{ $s->signer_name }} @if($s->role)<span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold text-white" data-role-chip="{{ $s->role }}">{{ $s->role }}</span>@endif</p>
                            <p class="truncate text-xs text-slate-400">{{ $s->signer_email }}</p>
                        </div>
                        <span class="badge {{ ['Signed' => 'badge-green', 'Waiting' => 'badge-blue', 'Declined' => 'badge-amber', 'Not sent yet' => 'badge-slate'][$s->statusLabel()] ?? 'badge-slate' }} shrink-0">{{ $s->statusLabel() }}</span>
                    </div>
                    <ul class="mt-1.5 space-y-0.5 text-[11px] text-slate-500">
                        @if($s->activated_at)<li><i data-lucide="send" class="mr-1 inline text-blue-500"></i>Sent {{ $s->activated_at->format('d M H:i') }}</li>@else<li><i data-lucide="clock" class="mr-1 inline"></i>Waits for the person before</li>@endif
                        @if($s->first_viewed_at)<li><i data-lucide="eye" class="mr-1 inline"></i>Opened {{ $s->first_viewed_at->format('d M H:i') }}@if($s->view_count > 1) · {{ $s->view_count }} visits, last {{ $s->last_viewed_at->format('d M H:i') }}@endif</li>
                        @elseif($s->activated_at && $s->status === 'pending')<li class="text-amber-600"><i data-lucide="eye" class="mr-1 inline"></i>Not opened yet</li>@endif
                        @if($s->reminder_count)<li><i data-lucide="bell" class="mr-1 inline text-amber-500"></i>{{ $s->reminder_count }} {{ Str::plural('reminder', $s->reminder_count) }}, last {{ $s->reminded_at->format('d M H:i') }}</li>@endif
                        @if($s->status === 'pending' && $s->activated_at)<li><i data-lucide="clock" class="mr-1 inline"></i>Next automatic reminder {{ ($s->reminded_at ?? $s->activated_at)->copy()->addDays($s->remind_after_days)->format('d M H:i') }} (every {{ $s->remind_after_days }} {{ Str::plural('day', $s->remind_after_days) }}, up to 3)</li>@endif
                        @if($s->status === 'signed')<li class="text-emerald-600"><i data-lucide="pen-line" class="mr-1 inline"></i>Signed {{ $s->signed_at->format('d M H:i') }}{{ $s->method ? ' · by '.['draw' => 'drawing', 'type' => 'typing', 'upload' => 'uploaded image', 'camera' => 'camera photo'][$s->method] : '' }}</li>@endif
                        @if($s->status === 'declined')<li class="text-amber-600"><i data-lucide="circle-x" class="mr-1 inline"></i>Declined{{ $s->decline_reason ? ': '.$s->decline_reason : '' }}</li>@endif
                    </ul>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @if($s->status === 'signed')<a href="{{ route('signatures.certificate', $s) }}" class="btn btn-ghost btn-sm">Certificate</a>@endif
                        @if($s->status === 'pending')
                            <form method="post" action="{{ route('signatures.remind', $s) }}" data-confirm-title="Send a reminder now?" data-confirm="{{ $s->signer_name }} will be emailed again." data-confirm-button="Yes, remind">@csrf<button class="btn btn-ghost btn-sm"><i data-lucide="bell"></i> Remind now</button></form>
                            <button type="button" data-copy="{{ route('sign.show', $s->token) }}" class="btn btn-ghost btn-sm">Copy link</button>
                        @endif
                        @if(in_array($s->status, ['pending', 'queued'], true))
                            <form method="post" action="{{ route('signatures.cancel', $s) }}" data-danger data-confirm-title="Cancel this request?" data-confirm-button="Yes, cancel">@csrf @method('DELETE')<button class="icon-btn hover:!text-red-600" title="Cancel"><i data-lucide="x"></i></button></form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endif

{{-- Who opened it, and everything that happened --}}
<div class="card card-pad text-sm" id="timeline-card">
    <h2 class="mb-1 font-semibold">Timeline</h2>
    <p class="mb-3 text-xs text-slate-500">Every step of this document, newest first: uploads, versions, signatures, workflow decisions and who opened it.</p>
    @if($openers->isNotEmpty())
        <div class="mb-3 rounded-lg bg-slate-50 p-2.5" id="opened-card">
            <p class="mb-1 text-xs font-semibold text-slate-600"><i data-lucide="eye" class="mr-1 inline"></i>Opened by</p>
            <ul class="space-y-0.5 text-xs text-slate-600">
                @foreach($openers as $o)<li class="flex justify-between gap-2"><span class="truncate">{{ $o['name'] }}</span><span class="shrink-0 text-slate-400">{{ $o['times'] }}× · last {{ $o['last']->format('d M H:i') }}</span></li>@endforeach
            </ul>
        </div>
    @endif
    <div class="mb-2 flex items-center gap-2 text-xs"><label class="flex items-center gap-1.5"><input type="checkbox" data-timeline-views> Show every time someone opened it</label></div>
    <ol class="relative space-y-3 border-l border-slate-200 pl-4" data-timeline>
        @foreach($timeline as $t)
            <li data-view="{{ $t['view'] ? 1 : 0 }}" class="{{ $t['view'] ? 'hidden' : '' }}">
                <span class="absolute -left-[0.65rem] flex size-5 items-center justify-center rounded-full bg-white ring-2 ring-slate-200"><i data-lucide="{{ $t['icon'] }}" class="!size-3 {{ $t['tone'] }}"></i></span>
                <p class="text-xs text-slate-700">{{ $t['text'] }}</p>
                <p class="text-[11px] text-slate-400">{{ $t['at']->format('d M Y H:i') }}@if($t['who']) · {{ $t['who'] }}@endif</p>
            </li>
        @endforeach
    </ol>
</div>

{{-- Versions --}}
<div class="card card-pad text-sm">
    <h2 class="mb-1 font-semibold">Versions</h2>
    <p class="mb-3 text-xs text-slate-500">Uploading a new file keeps the old one. Current file: {{ $document->humanSize() }}.</p>
    @if(auth()->user()->canOn($project, 'can_upload') && ! $document->legal_hold)
        <form method="post" action="{{ route('versions.store', $document) }}" enctype="multipart/form-data" data-confirm-title="Upload a new version?" data-confirm="The current file is kept in the version history." data-confirm-button="Yes, upload" class="space-y-2">
            @csrf
            <input type="file" name="file" required class="input p-1.5 text-xs">
            <input name="note" class="input" maxlength="300" placeholder="What changed? (optional)">
            <button class="btn btn-primary btn-sm">Upload new version</button>
        </form>
    @endif
    @if($versions->isNotEmpty())
        <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
            @foreach($versions as $v)
                <li class="flex items-center justify-between gap-2 py-2.5 text-xs">
                    <div class="min-w-0"><span class="font-medium text-slate-700">Version {{ $v->version_no }}</span> <span class="text-slate-400">· replaced {{ $v->replaced_at->format('d M Y H:i') }}</span>@if($v->note)<br><span class="text-slate-500">{{ $v->note }}</span>@endif</div>
                    <div class="flex shrink-0 gap-1">
                        @if($canDownload)<a href="{{ route('versions.download', $v) }}" data-no-ajax class="btn btn-ghost btn-sm">Download</a>@endif
                        @if(auth()->user()->canOn($project, 'can_upload') && ! $document->legal_hold)<form method="post" action="{{ route('versions.restore', $v) }}" data-confirm-title="Make version {{ $v->version_no }} current?" data-confirm="The file now shown is kept in the history." data-confirm-button="Yes, restore">@csrf<button class="btn btn-ghost btn-sm">Restore</button></form>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>

{{-- Retention and legal hold --}}
<div class="card card-pad text-sm">
    <h2 class="mb-3 font-semibold">Retention</h2>
    <p class="text-slate-600">
        @if($document->legal_hold) <span class="badge badge-amber">Legal hold</span> {{ $document->legal_hold_reason }}
        @elseif($document->disposal_date) Disposal date: <strong>{{ $document->disposal_date->format('d M Y') }}</strong>
        @else No retention schedule for this project. @endif
    </p>
    @if($me->isClientAdmin())
        @if($document->legal_hold)
            <form method="post" action="{{ route('documents.release', $document) }}" data-confirm-title="Release the legal hold?" data-confirm="The document can then be deleted or destroyed under its retention schedule." data-confirm-button="Yes, release" class="mt-3">
                @csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Release hold</button></form>
        @else
            <form method="post" action="{{ route('documents.hold', $document) }}" data-danger data-confirm-title="Place a legal hold?" data-confirm="Nobody, including admins, can delete or destroy it until you release the hold." data-confirm-button="Yes, place hold" class="mt-3 flex gap-2">
                @csrf <input name="reason" class="input" placeholder="Reason, e.g. Case 2026-14" required><button class="btn btn-ghost btn-sm">Hold</button></form>
        @endif
    @endif
</div>

{{-- Secure sharing --}}
@if($canDownload)
<div class="card card-pad text-sm">
    <h2 class="mb-1 font-semibold">Share securely</h2>
    <p class="mb-3 text-xs text-slate-500">The recipient gets a link, not the file. Index values and notes are never shown to them.</p>
    <form method="post" action="{{ route('share.store', $document) }}" data-confirm-title="Create a sharing link?" data-confirm-button="Yes, create" class="space-y-3">
        @csrf
        <div class="grid grid-cols-2 gap-2">
            <div><label class="label">Expires in (days)</label><input type="number" name="days" value="7" min="1" max="90" class="input" required></div>
            <div><label class="label">Access code <span class="font-normal text-slate-400">(optional)</span></label><input name="code" class="input" minlength="4" maxlength="20" autocomplete="off"></div>
        </div>
        <label class="flex items-center gap-2"><input type="checkbox" name="allow_download" value="1" class="rounded border-slate-300" checked> Allow download</label>
        <button class="btn btn-primary btn-sm">Create link</button>
    </form>

    @if($shares->isNotEmpty())
        <ul class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
            @foreach($shares as $s)
                <li class="flex items-center justify-between gap-2 py-2.5">
                    <div class="min-w-0 text-xs">
                        <span class="badge {{ $s->statusLabel() === 'Active' ? 'badge-green' : 'badge-slate' }}">{{ $s->statusLabel() }}</span>
                        <span class="text-slate-500">expires {{ $s->expires_at->format('d M') }} · {{ $s->opened_count }} opened · {{ $s->download_count }} downloaded {!! $s->code_hash ? '· code' : '' !!}</span>
                    </div>
                    @if($s->isUsable())
                    <div class="flex shrink-0 gap-1">
                        <button type="button" data-copy="{{ route('share.show', $s->token) }}" class="btn btn-ghost btn-sm">Copy link</button>
                        <form method="post" action="{{ route('share.revoke', $s) }}" data-danger data-confirm-title="Revoke this link?" data-confirm="It stops working immediately." data-confirm-button="Yes, revoke">
                            @csrf @method('DELETE')<button class="icon-btn hover:!text-red-600" title="Revoke"><i data-lucide="x"></i></button></form>
                    </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endif

@extends('layouts.app')
@section('title', $client->exists ? 'Edit client' : 'New client')

@section('content')
<div class="mb-6"><a href="{{ route('clients.index') }}" class="text-sm text-brand hover:underline">← Clients</a>
    <h1 class="mt-1 text-2xl font-bold">{{ $client->exists ? 'Edit '.$client->name : 'New client' }}</h1></div>

<form method="post" enctype="multipart/form-data" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="space-y-6">
    @csrf @if($client->exists) @method('PUT') @endif

    <section class="card card-pad">
        <h2 class="mb-4 font-semibold">Client details</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <div><label class="label">Client name</label><input name="name" value="{{ old('name', $client->name) }}" data-slug="#slug" class="input" required autofocus></div>
            <div><label class="label">Slug</label><input id="slug" name="slug" value="{{ old('slug', $client->slug) }}" class="input font-mono" required></div>
            <div><label class="label">Custom domain <span class="font-normal text-slate-400">(white-label)</span></label>
                <input name="domain" value="{{ old('domain', $client->domain) }}" class="input" placeholder="docs.clientcompany.com">
                <p class="mt-1 text-xs text-slate-500">Point this domain's DNS to the platform. Users who open it see only this client's brand.</p></div>
            <div><label class="label">Status</label>
                <select name="status" class="input">@foreach(['active','suspended','archived'] as $s)<option value="{{ $s }}" @selected(old('status', $client->status)===$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        </div>
    </section>

    @unless($client->exists)
    @php($bps = \App\Models\SetupBlueprint::orderBy('name')->orderByDesc('version')->get()->unique('name'))
    @php($svc = app(\App\Services\BlueprintService::class))
    <section class="card card-pad">
        <h2 class="mb-1 font-semibold">Start from a blueprint</h2>
        <p class="mb-4 text-sm text-slate-500">Optional. The client is created with that setup ready. If anything fails, nothing is created.</p>
        <select name="blueprint_id" id="bp-select" class="input max-w-md">
            <option value="">No blueprint (empty client)</option>
            @foreach($bps as $b)
                @php($sm = $svc->summary($b->data))
                <option value="{{ $b->id }}" data-preview="{{ $sm['departments'] }} departments, {{ $sm['projects'] }} projects ({{ implode(', ', $sm['project_names']) }}), {{ $sm['fields'] }} fields, {{ $sm['workflows'] }} workflows, {{ $sm['forms'] }} eForms, {{ $sm['doc_types'] }} document types" @selected((int) request('blueprint', old('blueprint_id')) === $b->id)>{{ $b->label() }}{{ $b->industry ? ' · '.$b->industry : '' }}</option>
            @endforeach
        </select>
        <p id="bp-preview" class="mt-3 hidden rounded-lg bg-brand-soft p-3 text-sm text-slate-700"></p>
        <script>
            (function () {
                const s = document.getElementById('bp-select'), p = document.getElementById('bp-preview');
                const show = () => { const t = s.selectedOptions[0]?.dataset.preview; p.textContent = t ? 'This will create: ' + t + '.' : ''; p.classList.toggle('hidden', !t); };
                s.addEventListener('change', show); show();
            })();
        </script>
    </section>
    @endunless

    <section class="card card-pad">
        <h2 class="mb-4 font-semibold">Branding</h2>
        @include('partials.branding-fields')
    </section>

    <section class="card card-pad">
        <h2 class="mb-1 font-semibold">Add-on modules</h2>
        <p class="mb-4 text-sm text-slate-500">Switched off modules stay hidden and cannot be reached by address.</p>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach(\App\Models\Client::MODULES as $key => [$label, $desc])
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                    <input type="checkbox" name="modules[{{ $key }}]" value="1" class="mt-1 rounded border-slate-300" @checked(old("modules.$key", $client->hasModule($key)))>
                    <span><span class="block text-sm font-semibold">{{ $label }}</span><span class="text-xs text-slate-500">{{ $desc }}</span></span>
                </label>
            @endforeach
        </div>
    </section>

    @unless($client->exists)
    <section class="card card-pad">
        <h2 class="mb-1 font-semibold">First Client Admin</h2>
        <p class="mb-4 text-sm text-slate-500">This person manages the client's users and settings.</p>
        <div class="grid gap-5 sm:grid-cols-3">
            <div><label class="label">Full name</label><input name="admin_name" value="{{ old('admin_name') }}" class="input" required></div>
            <div><label class="label">Email</label><input type="email" name="admin_email" value="{{ old('admin_email') }}" class="input" required></div>
            <div><label class="label">Temporary password</label><input type="password" name="admin_password" class="input" minlength="8" autocomplete="new-password" required></div>
        </div>
    </section>
    @endunless

    <div class="flex justify-end gap-3">
        <a href="{{ route('clients.index') }}" class="btn btn-ghost">Cancel</a>
        <button class="btn btn-primary">{{ $client->exists ? 'Save changes' : 'Create client' }}</button>
    </div>
</form>
@endsection

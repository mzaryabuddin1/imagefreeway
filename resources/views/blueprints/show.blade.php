@extends('layouts.app')
@section('title', $blueprint->label())

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><a href="{{ route('blueprints') }}" class="text-sm text-brand hover:underline">← Blueprints</a>
        <h1 class="mt-1 text-2xl font-bold">{{ $blueprint->name }} <span class="badge badge-blue align-middle">v{{ $blueprint->version }}</span></h1>
        <p class="text-sm text-slate-500">{{ $blueprint->industry ?: 'No industry set' }} · saved {{ $blueprint->created_at->format('d M Y') }}@if($blueprint->notes) · {{ $blueprint->notes }}@endif</p></div>
    <a href="{{ route('clients.create', ['blueprint' => $blueprint->id]) }}" class="btn btn-primary"><i data-lucide="plus"></i> New client from this</a>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
    @foreach([['Departments', $s['departments']], ['Projects', $s['projects']], ['Fields', $s['fields']], ['Workflows', $s['workflows']], ['eForms', $s['forms']], ['Document types', $s['doc_types']]] as [$l, $v])
        <div class="card card-pad"><div class="text-xs text-slate-500">{{ $l }}</div><div class="mt-1 text-2xl font-bold">{{ $v }}</div></div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card"><div class="border-b border-slate-100 px-5 py-3 font-semibold">Departments and projects</div>
        <ul class="divide-y divide-slate-100 text-sm">
            @foreach($blueprint->data['departments'] ?? [] as $d)
                <li class="px-5 py-3"><div class="font-medium">{{ $d['name'] }}</div>
                    @foreach($d['projects'] ?? [] as $p)<div class="ml-3 mt-1 text-xs text-slate-600">{{ $p['name'] }} <span class="text-slate-400">· {{ count($p['fields'] ?? []) }} fields @if(! empty($p['naming_pattern']))· names: {{ $p['naming_pattern'] }}@endif</span></div>@endforeach</li>
            @endforeach
        </ul></section>
    <section class="card"><div class="border-b border-slate-100 px-5 py-3 font-semibold">Workflows, modules and clients</div>
        <div class="space-y-3 p-5 text-sm">
            <div><div class="text-xs text-slate-500">Workflows</div>{{ implode(', ', $s['workflow_names']) ?: 'None' }}</div>
            <div><div class="text-xs text-slate-500">Add-on modules switched on</div>{{ $s['modules'] ? implode(', ', $s['modules']) : 'None' }}</div>
            <div><div class="text-xs text-slate-500">Clients made from this blueprint</div>@forelse($clients as $c)<a class="text-brand hover:underline" href="{{ route('support.show', $c) }}">{{ $c->name }}</a>@if(! $loop->last), @endif @empty None yet @endforelse</div>
            <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">People, documents and access rights are never copied. Workflow tasks start with no one assigned, so Client Admins receive them until the client assigns people.</p>
        </div></section>
</div>
@endsection

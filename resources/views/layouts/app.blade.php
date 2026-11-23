@php($b = \App\Support\Branding::current())
@php($u = auth()->user())
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $b->name }}</title>
    <style id="brand-vars">:root{--brand:{{ $b->primary }};--brand-dark:color-mix(in srgb,{{ $b->primary }} 82%,black);--brand-soft:color-mix(in srgb,{{ $b->primary }} 12%,white);--side:{{ $b->secondary }};--side-dark:color-mix(in srgb,{{ $b->secondary }} 68%,black);}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="ajax-bar"></div>
<div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

{{-- Sidebar --}}
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-gradient-to-b from-side to-side-dark text-white transition-transform lg:translate-x-0">
    <div class="flex h-16 shrink-0 items-center px-5">
        <a href="{{ route('dashboard') }}" class="flex h-10 items-center rounded-lg bg-white px-3">
            <img src="{{ $b->logoUrl }}" alt="{{ $b->name }}" class="max-h-7 w-auto max-w-[9.5rem]">
        </a>
    </div>
    <nav id="sidebar-nav" class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <a data-nav href="{{ route('dashboard') }}" class="nav-link"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        @if($u->isSuperAdmin())
            <a data-nav href="{{ route('clients.index') }}" class="nav-link"><i data-lucide="building-2"></i> Clients</a>
            <a data-nav href="{{ route('blueprints') }}" class="nav-link"><i data-lucide="copy"></i> Blueprints</a>
            <a data-nav href="{{ route('support') }}" class="nav-link"><i data-lucide="activity"></i> Support console</a>
        @endif
        @if($u->client_id)
            <a data-nav href="{{ route('documents') }}" class="nav-link"><i data-lucide="folder-open"></i> Documents</a>
            @if($u->isClientAdmin())
                <a data-nav href="{{ route('structure') }}" class="nav-link"><i data-lucide="layers"></i> Departments &amp; Projects</a>
            @endif
            <a data-nav href="{{ route('workflow') }}" class="nav-link"><i data-lucide="git-branch"></i> Workflow
                @php($waiting = \App\Models\WorkflowItem::waitingFor($u)->count())
                @if($waiting)<span class="ml-auto rounded-full bg-white/20 px-2 text-xs">{{ $waiting }}</span>@endif</a>
            <a data-nav href="{{ route('reports') }}" class="nav-link"><i data-lucide="bar-chart-3"></i> Reports</a>
            @if($u->isClientAdmin())
                <a data-nav href="{{ route('forms') }}" class="nav-link"><i data-lucide="file-signature"></i> eForms</a>
                <a data-nav href="{{ route('doctypes') }}" class="nav-link"><i data-lucide="tag"></i> Document types</a>
                <a data-nav href="{{ route('retention') }}" class="nav-link"><i data-lucide="clock"></i> Retention</a>
                @php($held = \App\Models\QuarantinedFile::where('status', 'held')->count())
                <a data-nav href="{{ route('quarantine') }}" class="nav-link"><i data-lucide="shield-alert"></i> Quarantine
                    @if($held)<span class="ml-auto rounded-full bg-red-500/80 px-2 text-xs">{{ $held }}</span>@endif</a>
            @endif
            <a data-nav href="{{ route('recycle') }}" class="nav-link"><i data-lucide="trash-2"></i> Recycle Bin</a>
        @endif
        @if($u->isAdmin())
            <a data-nav href="{{ route('users.index') }}" class="nav-link"><i data-lucide="users"></i> Users</a>
            @if($u->client_id)
                <a data-nav href="{{ route('branding.edit') }}" class="nav-link"><i data-lucide="palette"></i> Branding</a>
            @endif
            <a data-nav href="{{ route('audit') }}" class="nav-link"><i data-lucide="scroll-text"></i> Audit Log</a>
        @endif
        <a data-nav href="{{ route('notifications') }}" class="nav-link"><i data-lucide="bell"></i> Notifications</a>
        <a data-nav href="{{ route('help') }}" class="nav-link"><i data-lucide="book-open"></i> How to Use</a>
    </nav>
    <div class="border-t border-white/10 p-4 text-xs text-white/60">{{ $b->name }} · {{ $u->roleLabel() }}</div>
</aside>

<div class="lg:pl-64">
    @php($support = request()->attributes->get('support'))
    @if($support)
        <div class="sticky top-0 z-30 flex flex-wrap items-center justify-between gap-2 bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950">
            <span><i data-lucide="eye" class="mr-1 inline"></i> Support view: you are seeing the product as {{ $u->name }} ({{ $u->client?->name }}).
                {{ $support['acting'] ? 'Changes are ON until '.\Illuminate\Support\Carbon::createFromTimestamp($support['act_until'])->format('H:i').' and are recorded as support.' : 'Read-only.' }}</span>
            <span class="flex gap-2">
                @unless($support['acting'])
                    <form method="post" action="{{ route('support.act') }}" data-confirm-title="Enable changes for 30 minutes?" data-confirm="Anything you change will be done as {{ $u->name }}, marked as support in the audit trail." data-confirm-button="Yes, enable" data-danger>@csrf<button class="rounded bg-amber-950 px-3 py-1 text-xs text-white">Enable changes (30 min)</button></form>
                @endunless
                <form method="post" action="{{ route('support.stop') }}" data-no-confirm>@csrf<button class="rounded bg-white px-3 py-1 text-xs">Stop viewing</button></form>
            </span>
        </div>
    @endif
    {{-- Top bar --}}
    <header class="sticky z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-6">
        <button data-sidebar-toggle class="icon-btn lg:hidden" aria-label="Menu"><i data-lucide="menu"></i></button>
        @if($u->client_id)
            <form method="get" action="{{ route('search') }}" class="relative max-w-md flex-1" data-suggest="{{ route('search.suggest') }}" data-no-confirm>
                <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search every document you can see…" class="input pl-9" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="suggest-panel">
            </form>
        @endif
        <div class="flex-1"></div>

        <div class="relative">
            <button data-menu="notif" class="icon-btn relative" aria-label="Notifications">
                <i data-lucide="bell"></i>
                <span id="notif-badge" class="absolute -right-0.5 -top-0.5 hidden min-w-4 rounded-full bg-red-500 px-1 text-center text-[10px] font-bold leading-4 text-white">0</span>
            </button>
            <div data-menu-panel="notif" class="absolute right-0 mt-2 hidden w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <span class="text-sm font-semibold">Notifications</span>
                    <form method="post" action="{{ route('notifications.read-all') }}" data-no-confirm>@csrf
                        <button class="text-xs font-medium text-brand hover:underline">Mark all read</button>
                    </form>
                </div>
                <div id="notif-list" class="max-h-96 divide-y divide-slate-100 overflow-y-auto"></div>
                <a href="{{ route('notifications') }}" class="block border-t border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-brand hover:bg-slate-50">View all</a>
            </div>
        </div>

        <div class="relative">
            <button data-menu="user" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                <span class="inline-flex size-8 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                <span class="hidden text-left sm:block"><span class="block text-sm font-semibold leading-4">{{ $u->name }}</span><span class="text-xs text-slate-500">{{ $u->roleLabel() }}</span></span>
                <i data-lucide="chevron-down"></i>
            </button>
            <div data-menu-panel="user" class="absolute right-0 mt-2 hidden w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                <p class="truncate px-3 py-2 text-xs text-slate-500">{{ $u->email }}</p>
                <form method="post" action="{{ route('logout') }}" data-no-confirm>@csrf
                    <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100"><i data-lucide="log-out"></i> Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <main id="page" class="min-h-[calc(100vh-7.5rem)] p-4 lg:p-6">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 px-6 py-4 text-center text-xs text-slate-500">{{ $b->footer }}</footer>
</div>
@if($u)<div id="tour-root" data-key="{{ $u->id }}" data-steps="{{ json_encode(\App\Support\Tour::steps($u)) }}" data-guides="{{ json_encode(\App\Support\Tour::guides($u)) }}"></div>@endif
</body>
</html>

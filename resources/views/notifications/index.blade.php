@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="mb-6 flex items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Notifications</h1><p class="text-sm text-slate-500">Everything the system wanted you to know.</p></div>
    <form method="post" action="{{ route('notifications.read-all') }}" data-no-confirm>@csrf<button class="btn btn-ghost"><i data-lucide="check"></i> Mark all read</button></form>
</div>

<div class="card divide-y divide-slate-100">
    @forelse($items as $n)
        @php($tone = ['success' => 'text-emerald-600 bg-emerald-50', 'warning' => 'text-amber-600 bg-amber-50', 'danger' => 'text-red-600 bg-red-50'][$n->data['level'] ?? ''] ?? 'text-brand bg-brand-soft')
        <a href="{{ $n->data['url'] ?? '#' }}" data-notif="{{ $n->id }}" class="flex gap-4 px-5 py-4 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-brand-soft/40' }}">
            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full {{ $tone }}"><i data-lucide="{{ $n->data['icon'] ?? 'bell' }}"></i></span>
            <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $n->data['title'] ?? '' }}</span><span class="block text-sm text-slate-600">{{ $n->data['message'] ?? '' }}</span></span>
            <span class="shrink-0 text-xs text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
        </a>
    @empty
        <p class="px-5 py-12 text-center text-sm text-slate-400">You're all caught up.</p>
    @endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection

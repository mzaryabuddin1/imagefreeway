@extends('layouts.app')
@section('title', 'Recycle Bin')

@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold">Recycle Bin</h1><p class="text-sm text-slate-500">Deleted documents show who removed them and when. Restoring brings back everything, including index values.</p></div>

<div class="card overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">Document</th><th class="th">Project</th><th class="th">Deleted by</th><th class="th">Deleted</th><th class="th text-right">Action</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($documents as $d)
            <tr>
                <td class="td font-medium">{{ $d->original_name }}</td>
                <td class="td text-slate-600">{{ $d->project->department->name }} › {{ $d->project->name }}</td>
                <td class="td">{{ $d->deleter?->name ?? '—' }}</td>
                <td class="td text-slate-500">{{ $d->deleted_at->diffForHumans() }}</td>
                <td class="td text-right">
                    <form method="post" action="{{ route('recycle.restore', $d->id) }}" data-confirm-title="Restore this document?" data-confirm="{{ $d->original_name }} goes back to {{ $d->project->name }}." data-confirm-button="Yes, restore">
                        @csrf<button class="btn btn-ghost btn-sm"><i data-lucide="archive-restore"></i> Restore</button></form></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-400">The recycle bin is empty.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $documents->links() }}</div>
@endsection

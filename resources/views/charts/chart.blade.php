{{-- Modern chart (ApexCharts). @include('charts.chart', ['type' => 'area|donut|bar|column', 'data' => ['Label' => 3, ...], 'center' => 'Documents', 'prefix' => '$', 'name' => 'Documents']) --}}
@php
    $spec = [
        'type' => $type,
        'labels' => array_keys($data ?: []),
        'values' => array_values($data ?: []),
        'center' => $center ?? null,
        'prefix' => $prefix ?? '',
        'name' => $name ?? null,
    ];
@endphp
@if(! $data || array_sum($data) <= 0)
    <div class="flex h-40 flex-col items-center justify-center gap-1 text-center text-sm text-slate-400"><i data-lucide="bar-chart-3"></i>No data yet.</div>
@else
    <div data-chart="{{ json_encode($spec) }}" class="min-h-[8rem]"></div>
@endif

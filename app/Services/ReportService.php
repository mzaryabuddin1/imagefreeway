<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use App\Models\WorkflowItem;

/** The standard report (counts and totals by a field), shared by the Reports page and the scheduled emails. */
class ReportService
{
    /** @return array{rows:\Illuminate\Support\Collection,group:string,groupLabel:string,sumLabel:?string,workflowRows:\Illuminate\Support\Collection} */
    public function run(Project $project, string $group = 'status', string $sum = '', ?string $from = null, ?string $to = null): array
    {
        $project->loadMissing('fields');
        $fields = $project->fields;

        $q = Document::withoutGlobalScopes()->where('client_id', $project->client_id)->where('project_id', $project->id);
        $from && $q->whereDate('created_at', '>=', $from);
        $to && $q->whereDate('created_at', '<=', $to);

        $gField = $fields->firstWhere('key', $group);
        $sField = $fields->whereIn('type', ['number', 'amount', 'calculated'])->firstWhere('key', $sum);

        if ($group === 'month') {
            $expr = "DATE_FORMAT(created_at, '%Y-%m')";
            $groupLabel = 'Month added';
        } elseif ($gField) {
            $expr = "COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(index_values, '$.\"{$gField->key}\"')), 'null'), '(blank)')";
            $groupLabel = $gField->label;
        } else {
            $group = 'status';
            $expr = 'status';
            $groupLabel = 'Status';
        }
        $sumExpr = $sField ? "SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(index_values, '$.\"{$sField->key}\"')) AS DECIMAL(20,2)))" : 'NULL';

        $rows = $q->selectRaw("$expr as label, count(*) as total, $sumExpr as sum_value")->groupByRaw($expr)->orderByDesc('total')->limit(40)->get();

        $workflowRows = WorkflowItem::withoutGlobalScopes()->where('client_id', $project->client_id)
            ->whereHas('workflow', fn ($w) => $w->withoutGlobalScopes()->where('project_id', $project->id))
            ->with(['workflow' => fn ($w) => $w->withoutGlobalScopes()])->get()
            ->groupBy(fn ($i) => $i->workflow->name.' — '.($i->status === 'complete' ? ($i->result ?: 'Complete') : $i->nodeName()))
            ->map->count()->sortDesc();

        return ['rows' => $rows, 'group' => $group, 'groupLabel' => $groupLabel, 'sumLabel' => $sField?->label, 'workflowRows' => $workflowRows];
    }

    public function csv(array $r): string
    {
        $out = fopen('php://temp', 'w+');
        fputcsv($out, array_filter([$r['groupLabel'], 'Documents', $r['sumLabel'] ? "Total {$r['sumLabel']}" : null]));
        foreach ($r['rows'] as $row) {
            fputcsv($out, $r['sumLabel'] ? [$row->label, $row->total, $row->sum_value ?? 0] : [$row->label, $row->total]);
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}

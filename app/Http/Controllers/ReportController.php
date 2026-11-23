<?php

namespace App\Http\Controllers;

use App\Models\ScheduledReport;
use App\Services\ReportService;
use Illuminate\Http\Request;

/** Standard reports (F-REP-01): counts and totals driven by index values and workflow state. Not a report builder. */
class ReportController extends Controller
{
    public function index(Request $request, ReportService $service)
    {
        $user = $request->user();
        $projects = $user->projectsWith('can_view')->orderBy('name')->get();
        $project = $projects->firstWhere('id', (int) $request->query('project')) ?? $projects->first();

        $rows = collect();
        $fields = collect();
        $groupLabel = null;
        $sumLabel = null;
        $workflowRows = collect();
        $group = (string) $request->query('group', 'status');
        $sum = (string) $request->query('sum', '');
        $result = null;

        if ($project) {
            $project->load('fields');
            $fields = $project->fields;
            $result = $service->run($project, $group, $sum, $request->query('from') ?: null, $request->query('to') ?: null);
            ['rows' => $rows, 'group' => $group, 'groupLabel' => $groupLabel, 'sumLabel' => $sumLabel, 'workflowRows' => $workflowRows] = $result;
        }

        if ($request->query('export') === 'csv' && $project) {
            return response()->streamDownload(fn () => print ($service->csv($result)), 'report-'.str($project->name)->slug().'.csv', ['Content-Type' => 'text/csv']);
        }

        $schedules = $user->isClientAdmin() ? ScheduledReport::with('project')->latest()->get() : collect();

        return view('reports.index', compact('projects', 'project', 'fields', 'rows', 'group', 'sum', 'groupLabel', 'sumLabel', 'workflowRows', 'schedules'));
    }
}

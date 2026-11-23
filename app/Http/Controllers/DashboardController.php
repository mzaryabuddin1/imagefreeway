<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkflowItem;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $stats = [
            'users' => User::visibleTo($user)->where('status', 'active')->count(),
        ];
        $charts = [];

        if ($user->isSuperAdmin()) {
            $stats['clients'] = Client::where('status', 'active')->count();
            $stats['suspended'] = Client::where('status', 'suspended')->count();
            $stats['documents'] = Document::count();
            $stats['storage'] = round(Document::sum('size') / 1048576, 1);
            $charts = $this->platformCharts();
        } else {
            $stats['modules'] = collect($user->client?->modules ?? [])->filter()->count();
        }

        // "Unindexed documents" queue, per project the user may index
        $queues = collect();
        if ($user->client_id) {
            $ids = $user->projectsWith('can_index')->pluck('id');
            $queues = Document::whereIn('project_id', $ids)->where('status', 'unindexed')
                ->selectRaw('project_id, count(*) pending')->groupBy('project_id')->with('project.department')->get();
            $viewable = $user->projectsWith('can_view')->with('fields')->get();
            $stats['documents'] = Document::whereIn('project_id', $viewable->pluck('id'))->count();
            [$extra, $charts] = $this->clientCharts($user, $viewable);
            $stats += $extra;
        }

        $recent = $user->isAdmin() ? AuditLog::with('user', 'client')->latest()->limit(8)->get() : collect();

        return view('dashboard', compact('stats', 'recent', 'queues', 'charts'));
    }

    /** Documents added per day for the last N days, zero-filled. @return array<string,int> */
    private function perDay($query, int $days = 30): array
    {
        $from = today()->subDays($days - 1);
        $rows = (clone $query)->where('created_at', '>=', $from)->selectRaw('DATE(created_at) d, count(*) n')->groupBy('d')->pluck('n', 'd');
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            $out[$d->format('d M')] = (int) ($rows[$d->toDateString()] ?? 0);
        }

        return $out;
    }

    private function clientCharts($user, $viewable): array
    {
        $ids = $viewable->pluck('id');
        $docs = Document::whereIn('project_id', $ids);

        $status = (clone $docs)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $byProject = (clone $docs)->selectRaw('project_id, count(*) n')->groupBy('project_id')->orderByDesc('n')->limit(8)->pluck('n', 'project_id')
            ->mapWithKeys(fn ($n, $pid) => [$viewable->firstWhere('id', $pid)?->name ?? 'Project' => $n])->all();
        $allTypes = (clone $docs)->selectRaw("COALESCE(NULLIF(doc_type, ''), 'No type') t, count(*) n")->groupBy('t')->orderByDesc('n')->pluck('n', 't');
        $byType = $allTypes->take(6)->all();
        if ($allTypes->count() > 6) {
            $byType['Other types'] = (int) $allTypes->slice(6)->sum();
        }

        // Workflow: where items sit now, and how finished ones ended
        $items = WorkflowItem::whereHas('document', fn ($d) => $d->whereIn('project_id', $ids))->with('workflow')->get();
        $active = $items->where('status', 'active');
        $where = $active->groupBy(fn ($i) => $i->nodeName())->map->count()->sortDesc()->take(8)->all();
        $outcomes = $items->where('status', 'complete')->groupBy(fn ($i) => $i->result ?: 'Complete')->map->count()->sortDesc()->take(6)->all();

        // Amount per month from the first project that has an amount field
        $amountField = null;
        foreach ($viewable as $p) {
            if ($f = $p->fields->firstWhere('type', 'amount')) {
                $amountField = [$p, $f];
                break;
            }
        }
        $amounts = [];
        $amountLabel = null;
        if ($amountField) {
            [$p, $f] = $amountField;
            $amountLabel = "{$f->label} · {$p->name}";
            $key = $f->key;
            $rows = Document::where('project_id', $p->id)->where('created_at', '>=', today()->startOfMonth()->subMonths(5))
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') m, SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(index_values, '$.\"{$key}\"')) AS DECIMAL(20,2))) s")->groupBy('m')->pluck('s', 'm');
            for ($i = 5; $i >= 0; $i--) {
                $m = today()->startOfMonth()->subMonths($i);
                $amounts[$m->format('M Y')] = (float) ($rows[$m->format('Y-m')] ?? 0);
            }
        }

        $stats = [
            'unindexed' => (int) ($status['unindexed'] ?? 0),
            'waiting' => WorkflowItem::waitingFor($user)->count(),
            'overdue' => $active->filter->isOverdue()->count(),
            'storage' => round(Document::whereIn('project_id', $ids)->sum('size') / 1048576, 1),
        ];

        return [$stats, [
            'per_day' => $this->perDay($docs),
            'status' => ['Indexed' => (int) ($status['indexed'] ?? 0), 'Waiting to be indexed' => (int) ($status['unindexed'] ?? 0)],
            'by_project' => $byProject,
            'by_type' => $byType,
            'where' => $where,
            'outcomes' => $outcomes,
            'amounts' => $amounts,
            'amount_label' => $amountLabel,
        ]];
    }

    private function platformCharts(): array
    {
        $clients = Client::withCount('users')->get();
        $docsPer = Document::selectRaw('client_id, count(*) n, sum(size) bytes')->groupBy('client_id')->get()->keyBy('client_id');

        return [
            'per_day' => $this->perDay(Document::query()),
            'client_status' => $clients->groupBy('status')->map->count()->mapWithKeys(fn ($n, $s) => [ucfirst($s) => $n])->all(),
            'docs_per_client' => $clients->mapWithKeys(fn ($c) => [$c->name => (int) ($docsPer[$c->id]->n ?? 0)])->sortDesc()->take(8)->all(),
            'users_per_client' => $clients->mapWithKeys(fn ($c) => [$c->name => $c->users_count])->sortDesc()->take(8)->all(),
        ];
    }
}

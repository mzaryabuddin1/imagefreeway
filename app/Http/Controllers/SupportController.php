<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\QuarantinedFile;
use App\Models\User;
use App\Models\WorkflowItem;
use Illuminate\Http\Request;

/** Super Admin: every client with its usage and a health view (failed OCR, stuck workflows, quarantine, last activity). */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $rows = $this->rows($request->query('q'));

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Client', 'Status', 'Domain', 'Users', 'Documents', 'Storage (MB)', 'OCR failed', 'Overdue workflow items', 'Held in quarantine', 'Last login', 'Last upload']);
                foreach ($rows as $r) {
                    fputcsv($out, [$r->name, $r->status, $r->domain, $r->users, $r->documents, $r->storage_mb, $r->ocr_failed, $r->overdue, $r->quarantined, $r->last_login?->toDateTimeString(), $r->last_upload?->toDateTimeString()]);
                }
                fclose($out);
            }, 'client-usage-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
        }

        $totals = ['clients' => $rows->count(), 'documents' => $rows->sum('documents'), 'storage_mb' => round($rows->sum('storage_mb'), 1), 'attention' => $rows->where('needs_attention', true)->count()];

        return view('support.index', ['rows' => $rows, 'totals' => $totals, 'q' => $request->query('q')]);
    }

    /** Recent audit entries for one client, to see what is going on there. */
    public function show(Client $client)
    {
        $log = AuditLog::withoutGlobalScopes()->where('client_id', $client->id)->with('user')->latest()->limit(40)->get();
        $row = $this->rows(null)->firstWhere('id', $client->id);
        $failed = Document::withoutGlobalScopes()->where('client_id', $client->id)->where('ocr_status', 'failed')->latest()->limit(15)->get(['id', 'original_name', 'created_at']);

        $people = User::withoutGlobalScopes()->where('client_id', $client->id)->orderBy('role')->orderBy('name')->get();

        return view('support.show', compact('client', 'row', 'log', 'failed', 'people'));
    }

    /** Zip of the client's files, index values, audit trail and structure. Recorded in the client's audit trail. */
    public function export(Client $client, \App\Services\ClientExporter $exporter)
    {
        $path = $exporter->build($client);
        \App\Support\Audit::log('client.exported', null, 'Full data export downloaded by '.auth()->user()->name, [], $client->id);

        return response()->download($path, 'export-'.$client->slug.'-'.today()->toDateString().'.zip')->deleteFileAfterSend(true);
    }

    private function rows(?string $q)
    {
        $clients = Client::query()->when($q, fn ($w, $s) => $w->where(fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('domain', 'like', "%$s%")))->orderBy('name')->get();

        $docs = Document::withoutGlobalScopes()->selectRaw('client_id, count(*) n, coalesce(sum(size),0) bytes, max(created_at) last_upload, sum(ocr_status = "failed") ocr_failed')->groupBy('client_id')->get()->keyBy('client_id');
        $vers = DocumentVersion::withoutGlobalScopes()->selectRaw('client_id, coalesce(sum(size),0) bytes')->groupBy('client_id')->pluck('bytes', 'client_id');
        $users = User::withoutGlobalScopes()->whereNotNull('client_id')->selectRaw('client_id, count(*) n, max(last_login_at) last_login')->groupBy('client_id')->get()->keyBy('client_id');
        $held = QuarantinedFile::withoutGlobalScopes()->where('status', 'held')->selectRaw('client_id, count(*) n')->groupBy('client_id')->pluck('n', 'client_id');
        $overdue = WorkflowItem::withoutGlobalScopes()->where('status', 'active')->get()->filter->isOverdue()->groupBy('client_id')->map->count();

        return $clients->map(function ($c) use ($docs, $vers, $users, $held, $overdue) {
            $d = $docs[$c->id] ?? null;
            $c->users = (int) ($users[$c->id]->n ?? 0);
            $c->documents = (int) ($d->n ?? 0);
            $c->storage_mb = round((($d->bytes ?? 0) + ($vers[$c->id] ?? 0)) / 1048576, 1);
            $c->ocr_failed = (int) ($d->ocr_failed ?? 0);
            $c->overdue = (int) ($overdue[$c->id] ?? 0);
            $c->quarantined = (int) ($held[$c->id] ?? 0);
            $c->last_login = ($l = $users[$c->id]->last_login ?? null) ? \Illuminate\Support\Carbon::parse($l) : null;
            $c->last_upload = ($u = $d->last_upload ?? null) ? \Illuminate\Support\Carbon::parse($u) : null;
            $c->needs_attention = $c->ocr_failed > 0 || $c->overdue > 0 || $c->quarantined > 0;

            return $c;
        });
    }
}

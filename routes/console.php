<?php

use App\Models\Client;
use App\Models\User;
use App\Models\WorkflowItem;
use App\Services\WorkflowEngine;
use App\Support\Notice;
use App\Support\WdgTemplates;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminds assignees of overdue workflow items, then their managers (F-NOT-02)
Artisan::command('workflow:escalate', function (WorkflowEngine $engine) {
    $this->info($engine->escalate().' overdue item(s) escalated.');
})->purpose('Escalate overdue workflow items');

// Weekly management report: everything sitting in a flagged task, with its age
Artisan::command('workflow:digest', function () {
    $sent = 0;
    foreach (Client::all() as $client) {
        $rows = WorkflowItem::withoutGlobalScopes()->where('client_id', $client->id)->where('status', 'active')->whereNotNull('node_id')->with(['document', 'workflow'])->get()
            ->filter(fn ($i) => $i->document && ! empty($i->node()['config']['flag_field'] ?? ''))
            ->sortByDesc(fn ($i) => $i->stage_entered_at?->timestamp ? -$i->stage_entered_at->timestamp : 0);
        if ($rows->isEmpty()) {
            continue;
        }
        $lines = $rows->map(fn ($i) => sprintf('%-40s %-34s %3d days', mb_substr($i->document->original_name, 0, 40), mb_substr($i->workflow->name.' > '.$i->nodeName(), 0, 34), $i->stage_entered_at?->diffInDays(now()) ?? 0));
        $admins = User::withoutGlobalScopes()->where('client_id', $client->id)->where('role', 'client_admin')->where('status', 'active')->get();
        $body = "Items waiting in flagged tasks ({$rows->count()}), oldest first:\n\n".$lines->implode("\n");
        foreach ($admins as $a) {
            try {
                Mail::raw($body, fn ($m) => $m->to($a->email)->subject("Weekly management report: {$client->name}"));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        Notice::send($admins, 'Weekly management report', "{$rows->count()} item(s) waiting in flagged tasks. Details were emailed.", route('workflow', [], false), 'bar-chart-3', 'info');
        $sent++;
    }
    $this->info("Report sent for {$sent} client(s).");
})->purpose('Email the weekly flagged-items report with aging');

// Installs the five WDG processes (project, fields, people, document types, forms, workflows)
Artisan::command('wdg:install {client=demo : the client slug}', function (string $client) {
    $c = Client::where('slug', $client)->first();
    if (! $c) {
        return $this->error("No client with slug “{$client}”.");
    }
    $password = env('DEMO_PASSWORD') ?: bin2hex(random_bytes(6));
    $r = WdgTemplates::install($c, $password);
    $this->info("Installed in {$c->name}: project {$r['project']}; new workflows: ".(implode(', ', $r['workflows']) ?: 'none (already there)'));
    $this->line('People (password is DEMO_PASSWORD from .env, or this one for new accounts): '.implode(', ', $r['people']).' / '.$password);
})->purpose('Install the WDG workflows, forms and document types into a client');

// Scheduled reports (F-REP-02): daily, weekly (Monday) or monthly (1st), emailed with the client's branding
Artisan::command('reports:send', function (\App\Services\ScheduledReportSender $sender) {
    $n = 0;
    foreach (\App\Models\ScheduledReport::withoutGlobalScopes()->where('active', true)->get() as $r) {
        if ($r->isDueOn(today())) {
            $sender->send($r);
            $n++;
        }
    }
    $this->info("{$n} scheduled report(s) sent.");
})->purpose('Email the scheduled reports that are due today');

Artisan::command('signatures:remind', function (\App\Services\SigningService $s) {
    $this->info($s->sendDueReminders().' reminder(s) sent.');
})->purpose('Remind people who have been asked to sign but have not');

Schedule::command('signatures:remind')->hourly();
Schedule::command('reports:send')->dailyAt('07:00');
Schedule::command('workflow:escalate')->hourly();
Schedule::command('workflow:digest')->weeklyOn(5, '16:00');   // Fridays

require __DIR__.'/demo.php';

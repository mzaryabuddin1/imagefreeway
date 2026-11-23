<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ScheduledReport;
use App\Support\Audit;
use App\Support\Branding;
use Illuminate\Support\Facades\Mail;

class ScheduledReportSender
{
    public function __construct(private ReportService $reports) {}

    /** Emails the report to every valid recipient, branded for the client. Returns the number of recipients. */
    public function send(ScheduledReport $report): int
    {
        $project = $report->project()->withoutGlobalScopes()->first();
        $emails = $report->emails();
        if (! $project || ! $emails) {
            return 0;
        }
        $data = $this->reports->run($project, $report->group_by, (string) $report->sum_by);
        $brand = Branding::for(Client::find($report->client_id));
        $html = view('emails.report', compact('report', 'project', 'data', 'brand'))->render();
        $csv = $this->reports->csv($data);
        $subject = "{$brand->name}: {$report->name}";

        $sent = 0;
        foreach ($emails as $to) {
            try {
                Mail::html($html, function ($m) use ($to, $subject, $csv, $report) {
                    $m->to($to)->subject($subject)->attachData($csv, str($report->name)->slug().'.csv', ['mime' => 'text/csv']);
                });
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $report->forceFill(['last_sent_at' => now()])->save();
        Audit::log('report.sent', null, "Scheduled report “{$report->name}” sent to {$sent} recipient(s)", [], $report->client_id);

        return $sent;
    }
}

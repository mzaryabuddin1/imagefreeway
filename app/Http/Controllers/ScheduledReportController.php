<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Project;
use App\Models\ScheduledReport;
use App\Services\ScheduledReportSender;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Client Admin: reports emailed daily, weekly or monthly to a list of recipients (F-REP-02). */
class ScheduledReportController extends Controller
{
    use Responds;

    public function store(Request $request)
    {
        $d = $request->validate([
            'name' => 'required|string|max:120',
            'project_id' => ['required', Rule::exists('projects', 'id')->where('client_id', $request->user()->client_id)],
            'frequency' => ['required', Rule::in(array_keys(ScheduledReport::FREQUENCIES))],
            'group_by' => 'required|string|max:80',
            'sum_by' => 'nullable|string|max:80',
            'recipients' => 'required|string|max:1000',
        ], ['recipients.required' => 'Add at least one email address.']);

        $valid = array_filter(array_map('trim', preg_split('/[,;\s]+/', $d['recipients'])), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        $bad = array_diff(array_filter(array_map('trim', preg_split('/[,;\s]+/', $d['recipients']))), $valid);
        if (! $valid || $bad) {
            return $this->fail($bad ? 'These do not look like email addresses: '.implode(', ', $bad) : 'Add at least one email address.');
        }
        $report = ScheduledReport::create($d + ['created_by' => $request->user()->id, 'recipients' => implode(', ', $valid)]);
        Audit::log('report.scheduled', null, "Scheduled report “{$report->name}” created ({$report->frequency})", [], $report->client_id);

        return $this->ok('Scheduled. The first email goes out on the next due day.');
    }

    public function toggle(ScheduledReport $report)
    {
        $report->update(['active' => ! $report->active]);

        return $this->ok($report->active ? 'Resumed.' : 'Paused.');
    }

    public function sendNow(ScheduledReport $report, ScheduledReportSender $sender)
    {
        $n = $sender->send($report);

        return $n ? $this->ok("Sent to {$n} recipient(s).") : $this->fail('There was nobody to send it to.');
    }

    public function destroy(ScheduledReport $report)
    {
        $report->delete();

        return $this->ok('The scheduled report was deleted.');
    }
}

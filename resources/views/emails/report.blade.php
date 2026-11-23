<!doctype html>
<html><body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border-radius:10px;overflow:hidden">
    <tr><td style="background:{{ $brand->primary }};padding:18px 24px;color:#ffffff;font-size:18px;font-weight:bold">{{ $brand->name }}</td></tr>
    <tr><td style="padding:24px">
        <h1 style="margin:0 0 4px;font-size:20px">{{ $report->name }}</h1>
        <p style="margin:0 0 20px;color:#64748b;font-size:13px">{{ $project->name }} · {{ now()->format('d M Y') }} · documents by {{ strtolower($data['groupLabel']) }}</p>
        @php($max = max(1, (int) $data['rows']->max('total')))
        @forelse($data['rows'] as $r)
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px"><tr>
                <td style="font-size:14px;font-weight:bold">{{ $r->label }}</td>
                <td align="right" style="font-size:13px;color:#475569">{{ $r->total }}{{ $data['sumLabel'] ? ' · '.$data['sumLabel'].' '.number_format((float) $r->sum_value, 2) : '' }}</td></tr>
                <tr><td colspan="2" style="padding-top:4px"><div style="background:#e2e8f0;border-radius:6px;height:10px"><div style="background:{{ $brand->primary }};border-radius:6px;height:10px;width:{{ max(2, round($r->total / $max * 100)) }}%"></div></div></td></tr></table>
        @empty
            <p style="color:#94a3b8">No documents yet.</p>
        @endforelse
        @if($data['workflowRows']->isNotEmpty())
            <h2 style="margin:24px 0 8px;font-size:15px">Workflow state</h2>
            <table width="100%" cellpadding="6" cellspacing="0" style="font-size:13px;border-top:1px solid #e2e8f0">
                @foreach($data['workflowRows'] as $label => $n)<tr style="border-bottom:1px solid #e2e8f0"><td style="border-bottom:1px solid #e2e8f0">{{ $label }}</td><td align="right" style="border-bottom:1px solid #e2e8f0"><strong>{{ $n }}</strong></td></tr>@endforeach
            </table>
        @endif
        <p style="margin:24px 0 0;font-size:12px;color:#94a3b8">The full table is attached as a CSV file for Excel.</p>
    </td></tr>
    <tr><td style="background:#f8fafc;padding:12px 24px;font-size:11px;color:#94a3b8">{{ $brand->footer }}</td></tr>
</table></td></tr></table></body></html>

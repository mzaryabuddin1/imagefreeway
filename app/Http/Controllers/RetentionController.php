<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Document;
use App\Models\Project;
use App\Models\RetentionCertificate;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Retention schedules, review queue, legal holds and destruction certificates (Client Admin). */
class RetentionController extends Controller
{
    use Responds;

    public function index()
    {
        $projects = Project::with(['department', 'fields' => fn ($q) => $q->where('type', 'date')])->orderBy('name')->get();
        $due = Document::where('legal_hold', false)->whereNotNull('disposal_date')->whereDate('disposal_date', '<=', today())
            ->with('project')->orderBy('disposal_date')->limit(200)->get();
        $upcoming = Document::where('legal_hold', false)->whereBetween('disposal_date', [today()->addDay(), today()->addDays(90)])->count();
        $held = Document::where('legal_hold', true)->with('project')->orderBy('legal_hold_reason')->get();
        $certificates = RetentionCertificate::with('approver')->latest('destroyed_at')->limit(25)->get();

        return view('retention.index', compact('projects', 'due', 'upcoming', 'held', 'certificates'));
    }

    public function updateProject(Request $request, Project $project)
    {
        $data = $request->validate([
            'retention_years' => 'nullable|integer|min:1|max:100',
            'retention_field_id' => ['nullable', Rule::exists('project_fields', 'id')->where('project_id', $project->id)->where('type', 'date')],
        ]);
        $project->update(['retention_years' => $data['retention_years'] ?? null, 'retention_field_id' => ($data['retention_years'] ?? null) ? ($data['retention_field_id'] ?? null) : null]);

        $project->documents()->where('legal_hold', false)->chunkById(200, function ($docs) use ($project) {
            foreach ($docs as $d) {
                $d->recomputeDisposal($project);
                $d->saveQuietly();
            }
        });
        Audit::log('retention.updated', $project, "Retention for {$project->name}: ".($project->retention_years ? "{$project->retention_years} years" : 'none'));

        return $this->ok('Retention schedule saved. Disposal dates were recalculated.');
    }

    /** Review queue actions: destroy (needs approval), defer or place a hold. */
    public function review(Request $request)
    {
        $data = $request->validate([
            'do' => 'required|in:destroy,defer,hold',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'reason' => 'nullable|string|max:200',
        ], ['ids.required' => 'Tick at least one document first.', 'ids.min' => 'Tick at least one document first.']);

        $docs = Document::whereIn('id', $data['ids'])->get();
        $done = 0;

        foreach ($docs as $doc) {
            if ($data['do'] === 'hold') {
                $doc->update(['legal_hold' => true, 'legal_hold_reason' => $data['reason'] ?: 'Legal hold']);
                Audit::log('document.hold', $doc, "Legal hold placed on {$doc->original_name}", [], $doc->client_id);
                $done++;
            } elseif ($data['do'] === 'defer') {
                $doc->update(['disposal_date' => today()->addDays(90)]);
                Audit::log('retention.deferred', $doc, "Disposal of {$doc->original_name} deferred 90 days", [], $doc->client_id);
                $done++;
            } else {
                $done += $this->destroy($doc, $request->user()) ? 1 : 0;
            }
        }

        $verb = ['destroy' => 'destroyed', 'defer' => 'deferred by 90 days', 'hold' => 'placed on legal hold'][$data['do']];

        return $done ? $this->ok("$done ".str('document')->plural($done)." $verb.") : $this->fail('Nothing was changed. Held or not-yet-due documents cannot be destroyed.');
    }

    private function destroy(Document $doc, $user): bool
    {
        if ($doc->legal_hold || ! $doc->disposal_date || $doc->disposal_date->isFuture()) {
            return false;
        }
        $project = $doc->project;
        RetentionCertificate::create([
            'client_id' => $doc->client_id, 'project_id' => $project?->id, 'approved_by' => $user->id,
            'project_name' => $project?->name ?? '—', 'document_name' => $doc->original_name, 'document_id' => $doc->id,
            'index_values' => $doc->index_values, 'disposal_date' => $doc->disposal_date, 'destroyed_at' => now(),
        ]);
        $disk = Storage::disk(config('filesystems.documents_disk'));
        $disk->delete($doc->path);
        foreach (\App\Models\DocumentVersion::where('document_id', $doc->id)->get() as $v) {
            $disk->delete($v->path);
        }
        Audit::log('retention.destroyed', $doc, "Destroyed {$doc->original_name} under retention schedule", [], $doc->client_id);
        $doc->forceDelete();

        return true;
    }

    public function certificate(RetentionCertificate $certificate)
    {
        return view('retention.certificate', compact('certificate'));
    }

    /* ---------------- Legal hold (single document) ---------------- */

    public function hold(Request $request, Document $document)
    {
        $reason = $request->validate(['reason' => 'required|string|max:200'])['reason'];
        $document->update(['legal_hold' => true, 'legal_hold_reason' => $reason]);
        Audit::log('document.hold', $document, "Legal hold placed on {$document->original_name}: {$reason}", [], $document->client_id);

        return $this->ok('Legal hold placed. This document cannot be deleted or destroyed.');
    }

    public function release(Document $document)
    {
        $document->update(['legal_hold' => false, 'legal_hold_reason' => null]);
        Audit::log('document.hold_released', $document, "Legal hold released on {$document->original_name}", [], $document->client_id);

        return $this->ok('Legal hold released.');
    }
}

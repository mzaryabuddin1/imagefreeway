<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Jobs\ProcessDocumentOcr;
use App\Models\Document;
use App\Models\Project;
use App\Models\QuarantinedFile;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Client Admin: files that failed the virus scan. Release files them anyway; destroy deletes them for good. */
class QuarantineController extends Controller
{
    use Responds;

    private function disk()
    {
        return Storage::disk(config('filesystems.documents_disk'));
    }

    public function index()
    {
        $held = QuarantinedFile::with(['project', 'uploader'])->where('status', 'held')->latest()->get();
        $history = QuarantinedFile::with('project')->where('status', '!=', 'held')->latest('resolved_at')->limit(20)->get();

        return view('quarantine.index', compact('held', 'history'));
    }

    public function release(Request $request, QuarantinedFile $quarantined)
    {
        abort_unless($quarantined->status === 'held', 404);
        $project = $quarantined->project_id ? Project::find($quarantined->project_id) : null;
        if (! $project) {
            return $this->fail('This file has no project to be filed in, so it cannot be released. Destroy it instead.');
        }
        $ext = strtolower(pathinfo($quarantined->original_name, PATHINFO_EXTENSION)) ?: 'bin';
        $path = "clients/{$project->client_id}/projects/{$project->id}/".Str::uuid().".$ext";
        $this->disk()->move($quarantined->path, $path);

        $doc = Document::create([
            'client_id' => $project->client_id, 'project_id' => $project->id, 'uploaded_by' => $quarantined->user_id,
            'original_name' => $quarantined->original_name, 'mime' => $quarantined->mime, 'size' => $quarantined->size, 'path' => $path,
            'status' => $project->fields()->exists() ? 'unindexed' : 'indexed', 'ocr_status' => 'none', 'email_from' => $quarantined->source === 'email' ? $quarantined->source_detail : null,
        ]);
        $doc->recomputeDisposal($project);
        $doc->saveQuietly();
        $quarantined->update(['status' => 'released', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        $project->ocr_enabled && ProcessDocumentOcr::dispatchAfterResponse($doc->id);
        Audit::log('file.released', $doc, "Released {$quarantined->original_name} from quarantine ({$quarantined->threat})", [], $project->client_id);

        return $this->ok('Released and filed in '.$project->name.'.');
    }

    public function destroy(Request $request, QuarantinedFile $quarantined)
    {
        abort_unless($quarantined->status === 'held', 404);
        $this->disk()->delete($quarantined->path);
        $quarantined->update(['status' => 'destroyed', 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        Audit::log('file.destroyed', null, "Destroyed quarantined file {$quarantined->original_name} ({$quarantined->threat})", [], $quarantined->client_id);

        return $this->ok('The file was destroyed.');
    }
}

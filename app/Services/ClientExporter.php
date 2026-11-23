<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Department;
use App\Models\DocType;
use App\Models\Document;
use App\Models\Project;
use App\Models\Workflow;
use Illuminate\Support\Facades\Storage;

/** Everything a client owns, as a zip: original files, index values, audit trail and folder structure (F-OPS client data export). */
class ClientExporter
{
    /** Builds the zip in a temp file and returns its path. The caller deletes it. */
    public function build(Client $client): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'ifw-export');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $disk = Storage::disk(config('filesystems.documents_disk'));

        $projects = Project::withoutGlobalScopes()->where('client_id', $client->id)->with('fields')->get()->keyBy('id');
        $depts = Department::withoutGlobalScopes()->where('client_id', $client->id)->get()->keyBy('id');
        $docs = Document::withoutGlobalScopes()->withTrashed()->where('client_id', $client->id)->orderBy('id')->get();

        $keys = [];
        foreach ($docs as $d) {
            foreach (array_keys($d->index_values ?? []) as $k) {
                $keys[$k] = true;
            }
        }
        $keys = array_keys($keys);

        $index = fopen('php://temp', 'w+');
        fputcsv($index, array_merge(['Document ID', 'File in zip', 'Original name', 'Department', 'Project', 'Document type', 'Status', 'Added', 'Deleted'], $keys));
        $missing = [];
        foreach ($docs as $d) {
            $p = $projects[$d->project_id] ?? null;
            $dept = $p ? ($depts[$p->department_id]->name ?? '') : '';
            $folder = $this->safe($dept ?: 'No department').'/'.$this->safe($p->name ?? 'No project');
            $entry = "documents/{$folder}/{$d->id}-".$this->safe($d->original_name);
            if ($d->path && $disk->exists($d->path)) {
                $stream = $disk->readStream($d->path);
                $tmp = tempnam(sys_get_temp_dir(), 'ifw');
                $out = fopen($tmp, 'wb');
                stream_copy_to_stream($stream, $out);
                fclose($out);
                is_resource($stream) && fclose($stream);
                $zip->addFile($tmp, $entry);
                $missing[] = $tmp; // removed after close()
            } else {
                $entry = '(file missing)';
            }
            fputcsv($index, array_merge([$d->id, $entry, $d->original_name, $dept, $p->name ?? '', $d->doc_type, $d->status, $d->created_at, $d->deleted_at], array_map(fn ($k) => $d->index_values[$k] ?? '', $keys)));
        }
        rewind($index);
        $zip->addFromString('index-values.csv', (string) stream_get_contents($index));

        $audit = fopen('php://temp', 'w+');
        fputcsv($audit, ['When', 'User ID', 'Action', 'Description', 'IP']);
        AuditLog::withoutGlobalScopes()->where('client_id', $client->id)->orderBy('id')->chunk(500, function ($rows) use ($audit) {
            foreach ($rows as $a) {
                fputcsv($audit, [$a->created_at, $a->user_id, $a->action, $a->description, $a->ip]);
            }
        });
        rewind($audit);
        $zip->addFromString('audit-trail.csv', (string) stream_get_contents($audit));

        $structure = [
            'client' => $client->only(['name', 'slug', 'domain', 'status', 'modules']),
            'exported_at' => now()->toIso8601String(),
            'departments' => $depts->map(fn ($d) => ['name' => $d->name, 'projects' => $projects->where('department_id', $d->id)->map(fn ($p) => [
                'name' => $p->name, 'link_field' => $p->link_field, 'naming_pattern' => $p->naming_pattern,
                'fields' => $p->fields->map->only(['label', 'key', 'type', 'required', 'options', 'formula'])->values(),
            ])->values()])->values(),
            'document_types' => DocType::withoutGlobalScopes()->where('client_id', $client->id)->get(['name', 'email_alias'])->toArray(),
            'workflows' => Workflow::withoutGlobalScopes()->where('client_id', $client->id)->get(['name', 'description', 'graph'])->toArray(),
        ];
        $zip->addFromString('structure.json', json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('README.txt', "Export of {$client->name}, made ".now()->toDateTimeString()."\n\ndocuments/        the original files, grouped by department and project\nindex-values.csv  one row per document with its index values\naudit-trail.csv   the full audit trail\nstructure.json    departments, projects, fields, document types and workflows\n");

        $zip->close();
        array_map('unlink', $missing);

        return $zipPath;
    }

    private function safe(string $s): string
    {
        return trim(preg_replace('/[^\pL\pN\s._()\-&]+/u', '', $s)) ?: 'item';
    }
}

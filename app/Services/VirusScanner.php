<?php

namespace App\Services;

use App\Models\QuarantinedFile;
use App\Support\Audit;
use App\Support\Notice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class VirusScanner
{
    /** The harmless industry-standard test string every scanner flags. */
    private const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    /** Threat name, or null if clean. */
    public function scan(string $file): ?string
    {
        if (! config('scan.enabled')) {
            return null;
        }
        $head = (string) file_get_contents($file, false, null, 0, 1024 * 1024);
        if (str_contains($head, self::EICAR)) {
            return 'EICAR-Test-File';
        }
        $bin = config('scan.clamscan');
        if (! (is_file($bin) || (new ExecutableFinder)->find($bin))) {
            return null;
        }
        $p = new Process([$bin, '--no-summary', $file]);
        $p->setTimeout(config('scan.timeout'));
        $p->run();
        if ($p->getExitCode() === 1) {
            return trim(preg_replace('/^.*?:\s*|\s+FOUND\s*$/m', '', $p->getOutput())) ?: 'Malware';
        }
        if ($p->getExitCode() !== 0) {
            report(new \RuntimeException('Virus scanner failed: '.$p->getErrorOutput().$p->getOutput()));

            return config('scan.fail_closed') ? 'Scanner unavailable' : null;
        }

        return null;
    }

    /** Scans an upload. If it is infected the file is stored in quarantine and the record returned; clean files return null. */
    public function check(UploadedFile $file, int $clientId, ?int $projectId, ?int $userId, string $source, ?string $detail = null): ?QuarantinedFile
    {
        $threat = $this->scan($file->getRealPath());
        if (! $threat) {
            return null;
        }
        $name = Str::limit(preg_replace('/[^\pL\pN\s._()\-&]+/u', '', $file->getClientOriginalName()) ?: 'file', 180, '');
        $path = Storage::disk(config('filesystems.documents_disk'))->putFileAs("clients/{$clientId}/quarantine", $file, Str::uuid().'.quarantine');
        $q = QuarantinedFile::withoutGlobalScopes()->create([
            'client_id' => $clientId, 'project_id' => $projectId, 'user_id' => $userId, 'original_name' => $name,
            'mime' => $file->getClientMimeType(), 'size' => $file->getSize(), 'path' => $path, 'source' => $source, 'source_detail' => $detail, 'threat' => $threat,
        ]);
        Audit::log('file.quarantined', null, "{$name} held in quarantine ({$threat}) from {$source}", [], $clientId);
        Notice::send(Notice::clientAdmins($clientId), 'File held in quarantine', "{$name} failed the virus scan ({$threat}) and was not filed.", route('quarantine', [], false), 'shield-alert', 'danger');

        return $q;
    }
}

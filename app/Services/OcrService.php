<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class OcrService
{
    /** Extracts searchable text and stores it on the document. Never throws. */
    public function process(Document $doc): void
    {
        $tmp = null;
        try {
            $tmp = $this->localCopy($doc);
            [$text, $status] = $this->extract($tmp, $doc);
            $doc->forceFill(['ocr_text' => $text !== '' ? $text : null, 'ocr_status' => $status])->save();
        } catch (\Throwable $e) {
            report($e);
            $doc->forceFill(['ocr_status' => 'failed'])->save();
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /** @return array{0:string,1:string} [text, status] */
    private function extract(string $file, Document $doc): array
    {
        $ext = strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION));

        if (in_array($ext, ['txt', 'csv'], true)) {
            return [mb_substr((string) file_get_contents($file), 0, 500000), 'done'];
        }
        if ($ext === 'docx') {
            return [$this->docx($file), 'done'];
        }
        if ($ext === 'pdf') {
            return $this->pdf($file);
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'tif', 'tiff', 'bmp', 'webp'], true)) {
            return $this->image($file);
        }

        return ['', 'none'];
    }

    private function pdf(string $file): array
    {
        if ($this->has('pdftotext')) {
            $text = $this->run([config('ocr.pdftotext'), '-layout', '-enc', 'UTF-8', $file, '-']);
            if (mb_strlen(trim($text)) > 20) {
                return [$this->clean($text), 'done'];
            }
        }
        // Scanned PDF: rasterise pages, then OCR them
        if (! $this->has('pdftoppm') || ! $this->has('tesseract')) {
            return ['', 'unavailable'];
        }
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ifw_'.bin2hex(random_bytes(6));
        $this->run([config('ocr.pdftoppm'), '-r', '200', '-l', (string) config('ocr.max_pdf_pages'), '-png', $file, $prefix]);
        $out = '';
        foreach (glob($prefix.'*.png') ?: [] as $png) {
            $out .= $this->run([config('ocr.tesseract'), $png, 'stdout', '-l', config('ocr.language')])."\n";
            @unlink($png);
        }

        return [$this->clean($out), 'done'];
    }

    private function image(string $file): array
    {
        if (! $this->has('tesseract')) {
            return ['', 'unavailable'];
        }

        return [$this->clean($this->run([config('ocr.tesseract'), $file, 'stdout', '-l', config('ocr.language')])), 'done'];
    }

    private function docx(string $file): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($file) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $this->clean(html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1));
    }

    private function has(string $tool): bool
    {
        $bin = config("ocr.$tool");

        return is_file($bin) || (new ExecutableFinder)->find($bin) !== null;
    }

    private function run(array $cmd): string
    {
        $p = new Process($cmd);
        $p->setTimeout(config('ocr.timeout'));
        $p->run();

        return $p->isSuccessful() ? $p->getOutput() : '';
    }

    private function clean(string $t): string
    {
        return mb_substr(trim(preg_replace('/[ \t]+/', ' ', mb_convert_encoding($t, 'UTF-8', 'UTF-8'))), 0, 500000);
    }

    /** Works for both local and S3 disks. */
    private function localCopy(Document $doc): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ifw');
        $in = Storage::disk(config('filesystems.documents_disk'))->readStream($doc->path);
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        is_resource($in) && fclose($in);

        return $tmp;
    }
}

<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Turns document pages into images (for marking up) and burns redaction boxes in for good. PDFs need poppler; images need only GD. */
class PageService
{
    public const DPI = 110;

    public const MAX_PAGES = 50;

    public function available(Document $doc): bool
    {
        return $doc->isImage() || ($doc->isPdf() && $this->has('pdftoppm'));
    }

    public function pageCount(Document $doc): int
    {
        if ($doc->isImage()) {
            return 1;
        }
        $local = $this->localCopy($doc);
        try {
            if ($this->has('pdfinfo')) {
                $p = new Process([config('ocr.pdfinfo'), $local]);
                $p->setTimeout(30);
                $p->run();
                if (preg_match('/^Pages:\s+(\d+)/m', $p->getOutput(), $m)) {
                    return min((int) $m[1], self::MAX_PAGES);
                }
            }
            preg_match_all('/\/Type\s*\/Page[^s]/', (string) file_get_contents($local), $m);

            return max(1, min(count($m[0]), self::MAX_PAGES));
        } finally {
            @unlink($local);
        }
    }

    /** @return array{0:string,1:string}|null [bytes, mime] of one page (1-based) */
    public function page(Document $doc, int $n): ?array
    {
        if ($doc->isImage()) {
            return [Storage::disk(config('filesystems.documents_disk'))->get($doc->path), $doc->mime ?: 'image/png'];
        }
        if (! $this->available($doc)) {
            return null;
        }
        $cache = storage_path("app/page-cache/{$doc->id}-".substr(md5($doc->path), 0, 10)."-$n.png");
        if (! is_file($cache)) {
            @mkdir(dirname($cache), 0775, true);
            $local = $this->localCopy($doc);
            $prefix = $cache.'.tmp';
            $this->run([config('ocr.pdftoppm'), '-r', (string) self::DPI, '-f', (string) $n, '-l', (string) $n, '-png', '-singlefile', $local, $prefix]);
            @unlink($local);
            if (! is_file($prefix.'.png')) {
                return null;
            }
            rename($prefix.'.png', $cache);
        }

        return [file_get_contents($cache), 'image/png'];
    }

    /**
     * A new file with the boxes blacked out. A PDF is rebuilt from page pictures, so the hidden text is gone for good.
     *
     * @param  array<int, array<int, array{0:float,1:float,2:float,3:float}>>  $boxes  page => [[x,y,w,h], ...] as fractions
     * @return array{0:string,1:string,2:string} [bytes, mime, extension]
     */
    public function redact(Document $doc, array $boxes, ?string $signPng = null): array
    {
        if ($doc->isImage()) {
            $im = imagecreatefromstring(Storage::disk(config('filesystems.documents_disk'))->get($doc->path));
            abort_if($im === false, 422, 'The image could not be read.');
            $signPng ? $this->stamp($im, $boxes[1] ?? [], $signPng) : $this->blacken($im, $boxes[1] ?? []);
            $jpeg = in_array(strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg'], true);
            ob_start();
            $jpeg ? imagejpeg($im, null, 92) : imagepng($im);

            return [ob_get_clean(), $jpeg ? 'image/jpeg' : 'image/png', $jpeg ? 'jpg' : 'png'];
        }

        abort_unless($this->has('pdftoppm'), 422, 'PDF redaction needs poppler (pdftoppm) on the server.');
        $local = $this->localCopy($doc);
        $dir = sys_get_temp_dir().'/ifw-'.uniqid();
        mkdir($dir);
        try {
            $this->run([config('ocr.pdftoppm'), '-r', '150', '-l', (string) self::MAX_PAGES, '-png', $local, "$dir/p"]);
            $files = glob("$dir/p*.png") ?: [];
            natsort($files);
            $pages = [];
            foreach (array_values($files) as $i => $f) {
                $im = imagecreatefrompng($f);
                $signPng ? $this->stamp($im, $boxes[$i + 1] ?? [], $signPng) : $this->blacken($im, $boxes[$i + 1] ?? []);
                ob_start();
                imagejpeg($im, null, 85);
                $pages[] = [ob_get_clean(), imagesx($im), imagesy($im)];
            }
            abort_if(! $pages, 422, 'The pages could not be read.');

            return [$this->buildPdf($pages), 'application/pdf', 'pdf'];
        } finally {
            array_map('unlink', glob("$dir/*") ?: []);
            @rmdir($dir);
            @unlink($local);
        }
    }

    /** Draws a signature picture inside each box, keeping its shape and centring it. */
    private function stamp(\GdImage $im, array $boxes, string $png): void
    {
        $sig = @imagecreatefromstring($png);
        if ($sig === false) {
            return;
        }
        imagealphablending($im, true);
        $W = imagesx($im);
        $H = imagesy($im);
        $sw = imagesx($sig);
        $sh = imagesy($sig);
        foreach ($boxes as [$x, $y, $w, $h]) {
            $bw = max(4, (int) round($w * $W));
            $bh = max(4, (int) round($h * $H));
            $scale = min($bw / $sw, $bh / $sh);
            $dw = max(1, (int) round($sw * $scale));
            $dh = max(1, (int) round($sh * $scale));
            imagecopyresampled($im, $sig, (int) round($x * $W + ($bw - $dw) / 2), (int) round($y * $H + ($bh - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
        }
    }

    private function blacken(\GdImage $im, array $boxes): void
    {
        $black = imagecolorallocate($im, 0, 0, 0);
        $W = imagesx($im);
        $H = imagesy($im);
        foreach ($boxes as [$x, $y, $w, $h]) {
            imagefilledrectangle($im, (int) floor($x * $W), (int) floor($y * $H), (int) ceil(($x + $w) * $W), (int) ceil(($y + $h) * $H), $black);
        }
    }

    /** A minimal valid PDF: one JPEG per page at 150 dpi. */
    private function buildPdf(array $pages): string
    {
        $objs = [];
        $kids = [];
        foreach ($pages as $i => [$jpg, $w, $h]) {
            $base = 3 + $i * 3; // image, content, page
            $pw = round($w * 72 / 150, 2);
            $ph = round($h * 72 / 150, 2);
            $objs[$base] = "<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($jpg)." >>\nstream\n$jpg\nendstream";
            $c = "q $pw 0 0 $ph 0 0 cm /Im0 Do Q";
            $objs[$base + 1] = '<< /Length '.strlen($c)." >>\nstream\n$c\nendstream";
            $objs[$base + 2] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pw $ph] /Contents ".($base + 1)." 0 R /Resources << /XObject << /Im0 $base 0 R >> >> >>";
            $kids[] = ($base + 2).' 0 R';
        }
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        ksort($objs);
        $out = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $id => $body) {
            $off[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref'."\n0 ".(count($objs) + 1)."\n0000000000 65535 f \n";
        foreach ($off as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }

        return $out.'trailer << /Size '.(count($objs) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }

    private function has(string $tool): bool
    {
        $bin = config("ocr.$tool");

        return is_file($bin) || (new ExecutableFinder)->find($bin) !== null;
    }

    private function run(array $cmd): void
    {
        $p = new Process($cmd);
        $p->setTimeout(config('ocr.timeout'));
        $p->run();
    }

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

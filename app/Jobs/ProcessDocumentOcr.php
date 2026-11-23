<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\OcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDocumentOcr implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $documentId) {}

    public function handle(OcrService $ocr): void
    {
        $doc = Document::withoutGlobalScopes()->find($this->documentId);
        if ($doc) {
            $ocr->process($doc);
        }
    }
}

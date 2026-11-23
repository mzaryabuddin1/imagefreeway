<?php

/*
| Text extraction for search. Every binary is optional: if one is missing, that
| kind of file is marked "unavailable" instead of failing the upload.
|   tesseract  - OCR for scanned pages and images   (https://github.com/tesseract-ocr/tesseract)
|   pdftotext  - text layer of digital PDFs          (poppler)
|   pdftoppm   - turns scanned PDF pages into images (poppler)
| Put the full path in .env if the binary is not on PATH.
*/
return [
    'tesseract' => env('OCR_TESSERACT', 'tesseract'),
    'pdftotext' => env('OCR_PDFTOTEXT', 'pdftotext'),
    'pdftoppm' => env('OCR_PDFTOPPM', 'pdftoppm'),
    'pdfinfo' => env('OCR_PDFINFO', 'pdfinfo'),
    'language' => env('OCR_LANG', 'eng'),
    'max_pdf_pages' => (int) env('OCR_MAX_PDF_PAGES', 20),
    'timeout' => (int) env('OCR_TIMEOUT', 120),
];

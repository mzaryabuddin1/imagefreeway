<?php

/*
| Virus scanning on every intake route (upload, new version, email-in).
| Infected files are held in quarantine, never filed.
|   SCAN_ENABLED       turn scanning on or off
|   SCAN_CLAMSCAN      path to clamscan or clamdscan (https://www.clamav.net). If it is not found, only the
|                      built-in test signature (the standard EICAR test file) is checked.
|   SCAN_FAIL_CLOSED   true = hold a file when the scanner itself breaks; false = let it through and log
*/
return [
    'enabled' => env('SCAN_ENABLED', true),
    'clamscan' => env('SCAN_CLAMSCAN', 'clamdscan'),
    'fail_closed' => env('SCAN_FAIL_CLOSED', false),
    'timeout' => (int) env('SCAN_TIMEOUT', 60),
];

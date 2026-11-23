@php
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    [$color, $label] = match (true) {
        $ext === 'pdf' => ['#e11d48', 'PDF'],
        in_array($ext, ['doc', 'docx', 'rtf', 'odt'], true) => ['#2563eb', 'DOC'],
        in_array($ext, ['xls', 'xlsx', 'ods'], true) => ['#16a34a', 'XLS'],
        $ext === 'csv' => ['#16a34a', 'CSV'],
        in_array($ext, ['ppt', 'pptx'], true) => ['#ea580c', 'PPT'],
        in_array($ext, ['jpg', 'jpeg'], true) => ['#9333ea', 'JPG'],
        $ext === 'png' => ['#7c3aed', 'PNG'],
        in_array($ext, ['gif', 'bmp', 'webp'], true) => ['#9333ea', strtoupper($ext)],
        in_array($ext, ['tif', 'tiff'], true) => ['#9333ea', 'TIF'],
        $ext === 'txt' => ['#64748b', 'TXT'],
        in_array($ext, ['zip', 'rar', '7z'], true) => ['#ca8a04', 'ZIP'],
        default => ['#64748b', strtoupper(substr($ext ?: 'file', 0, 4))],
    };
@endphp
<svg viewBox="0 0 64 80" class="{{ $class ?? 'h-16 w-14' }}" aria-hidden="true">
    <path d="M6 4a4 4 0 0 1 4-4h30l18 18v58a4 4 0 0 1-4 4H10a4 4 0 0 1-4-4z" fill="#fff" stroke="#cbd5e1" stroke-width="2"/>
    <path d="M40 0l18 18H44a4 4 0 0 1-4-4z" fill="#e2e8f0"/>
    <rect x="6" y="44" width="52" height="22" rx="3" fill="{{ $color }}"/>
    <text x="32" y="60" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="{{ strlen($label) > 3 ? 11 : 13 }}" fill="#fff">{{ $label }}</text>
    <path d="M16 22h28M16 29h28M16 36h18" stroke="#e2e8f0" stroke-width="3" stroke-linecap="round"/>
</svg>

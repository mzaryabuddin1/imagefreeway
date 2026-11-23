@php($b = $brand ?? \App\Support\Branding::current())
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · {{ $b->name }}</title>
    <style id="brand-vars">:root{--brand:{{ $b->primary }};--brand-dark:color-mix(in srgb,{{ $b->primary }} 82%,black);--brand-soft:color-mix(in srgb,{{ $b->primary }} 12%,white);--side:{{ $b->secondary }};--side-dark:color-mix(in srgb,{{ $b->secondary }} 68%,black);}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div id="ajax-bar"></div>
@yield('content')
</body>
</html>

{{-- Shared by the Super Admin client form and the Client Admin branding page --}}
@php
    $primary = old('primary_color', $client->primary_color ?: \App\Models\Client::DEFAULT_PRIMARY);
    $secondary = old('secondary_color', $client->secondary_color ?: \App\Models\Client::DEFAULT_SECONDARY);
@endphp
<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label class="label">Display name</label>
        <input name="app_name" value="{{ old('app_name', $client->app_name) }}" class="input" placeholder="Shown in the header and browser tab">
    </div>
    <div>
        <label class="label">Footer text</label>
        <input name="footer_text" value="{{ old('footer_text', $client->footer_text) }}" class="input" placeholder="© 2026 Your company">
    </div>
    <div>
        <label class="label">Primary colour (buttons, active menu)</label>
        <div class="flex gap-2">
            <input type="color" id="pc" value="{{ $primary }}" data-hex="#pc-hex" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
            <input id="pc-hex" name="primary_color" value="{{ $primary }}" data-color="#pc" class="input font-mono" maxlength="7">
        </div>
    </div>
    <div>
        <label class="label">Secondary colour (sidebar)</label>
        <div class="flex gap-2">
            <input type="color" id="sc" value="{{ $secondary }}" data-hex="#sc-hex" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
            <input id="sc-hex" name="secondary_color" value="{{ $secondary }}" data-color="#sc" class="input font-mono" maxlength="7">
        </div>
    </div>
    <div class="sm:col-span-2">
        <label class="label">Logo (PNG, SVG, JPG or WebP, up to 2 MB)</label>
        <div class="flex items-center gap-4">
            <span class="flex h-16 w-40 items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white p-2">
                <img id="logo-preview" src="{{ \App\Support\Branding::for($client->exists ? $client : null)->logoUrl }}" class="max-h-12 max-w-full" alt="Logo">
            </span>
            <input type="file" name="logo" accept="image/*" data-preview="#logo-preview" class="text-sm file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
            @if($client->logo_path)
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300"> Remove logo</label>
            @endif
        </div>
    </div>
</div>

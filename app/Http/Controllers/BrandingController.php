<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Client;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    use Responds;

    /** Serves a client's logo from whichever disk is configured (local or S3). Public so the login page can show it. */
    public function logo(Client $client)
    {
        abort_unless($client->logo_path, 404);
        $disk = Storage::disk(config('filesystems.documents_disk'));
        abort_unless($disk->exists($client->logo_path), 404);

        return $disk->response($client->logo_path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function edit()
    {
        $client = $this->ownClient();

        return view('branding.edit', compact('client'));
    }

    public function update(Request $request)
    {
        $client = $this->ownClient();
        $data = $request->validate([
            'app_name' => 'nullable|string|max:80',
            'primary_color' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'footer_text' => 'nullable|string|max:200',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $client->update(collect($data)->except('logo')->map(fn ($v) => $v === '' ? null : $v)->all());

        $disk = Storage::disk(config('filesystems.documents_disk'));
        if (($request->boolean('remove_logo') || $request->hasFile('logo')) && $client->logo_path) {
            $disk->delete($client->logo_path);
            $client->update(['logo_path' => null]);
        }
        if ($request->hasFile('logo')) {
            $client->update(['logo_path' => $disk->putFile("clients/{$client->id}/branding", $request->file('logo'))]);
        }

        Audit::log('branding.updated', $client, 'Branding updated');

        return $this->ok('Branding saved.');
    }

    private function ownClient(): Client
    {
        $client = auth()->user()->client;
        abort_unless($client, 403, 'Super Admins edit branding from the Clients page.');

        return $client;
    }
}

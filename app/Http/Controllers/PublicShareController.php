<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Document;
use App\Models\ShareLink;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/** Public page a recipient opens from a sharing link. No sign-in; the token is the key. */
class PublicShareController extends Controller
{
    private function resolve(string $token): array
    {
        $link = ShareLink::withoutGlobalScopes()->where('token', $token)->first();
        $doc = $link ? Document::withoutGlobalScopes()->find($link->document_id) : null;
        $client = $doc ? Client::find($doc->client_id) : null;

        abort_unless($link && $doc && $client?->isActive(), 404);
        if (! $link->isUsable()) {
            abort(410, $link->revoked_at ? 'This link has been revoked.' : 'This link has expired.');
        }

        return [$link, $doc, $client];
    }

    private function unlocked(Request $request, ShareLink $link): bool
    {
        return $link->code_hash === null || $request->session()->get('share_ok.'.$link->id) === true;
    }

    public function show(Request $request, string $token)
    {
        [$link, $doc, $client] = $this->resolve($token);
        $unlocked = $this->unlocked($request, $link);

        if ($unlocked) {
            $link->increment('opened_count');
            $link->update(['last_opened_at' => now()]);
            Audit::log('share.opened', $doc, "Sharing link opened for {$doc->original_name}", [], $doc->client_id);
        }

        $brand = \App\Support\Branding::for($client);

        return view('share.show', compact('link', 'doc', 'client', 'unlocked', 'token', 'brand'));
    }

    public function unlock(Request $request, string $token)
    {
        [$link] = $this->resolve($token);
        $code = (string) $request->input('code');

        if (! Hash::check($code, (string) $link->code_hash)) {
            return back()->withErrors(['code' => 'That access code is not right.']);
        }
        $request->session()->put('share_ok.'.$link->id, true);

        return redirect()->route('share.show', $token);
    }

    public function file(Request $request, string $token)
    {
        [$link, $doc] = $this->resolve($token);
        abort_unless($this->unlocked($request, $link), 403);

        return $this->stream($doc, 'inline');
    }

    public function download(Request $request, string $token)
    {
        [$link, $doc] = $this->resolve($token);
        abort_unless($this->unlocked($request, $link) && $link->allow_download, 403);
        $link->increment('download_count');
        Audit::log('share.downloaded', $doc, "Shared document downloaded: {$doc->original_name}", [], $doc->client_id);

        return $this->stream($doc, 'attachment');
    }

    private function stream(Document $doc, string $disposition)
    {
        $disk = Storage::disk(config('filesystems.documents_disk'));
        abort_unless($disk->exists($doc->path), 404);

        return $disk->response($doc->path, $doc->original_name, ['Content-Type' => $doc->mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'], $disposition);
    }
}

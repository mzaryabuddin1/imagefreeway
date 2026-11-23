<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Document;
use App\Models\ShareLink;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Creating and revoking secure sharing links (signed-in users). */
class ShareController extends Controller
{
    use Responds;

    public function store(Request $request, Document $document)
    {
        abort_unless($request->user()->canOn($document->project, 'can_download'), 403, 'You cannot share documents in this project.');
        $data = $request->validate([
            'days' => 'required|integer|min:1|max:90',
            'code' => 'nullable|string|min:4|max:20',
        ]);

        $link = ShareLink::create([
            'client_id' => $document->client_id,
            'document_id' => $document->id,
            'created_by' => $request->user()->id,
            'token' => Str::random(48),
            'code_hash' => filled($data['code'] ?? null) ? Hash::make($data['code']) : null,
            'allow_download' => $request->boolean('allow_download'),
            'expires_at' => now()->addDays((int) $data['days']),
        ]);
        Audit::log('document.shared', $document, "Created a sharing link for {$document->original_name} (expires {$link->expires_at->format('d M Y')})", [], $document->client_id);

        return $this->ok('Sharing link created. Copy it from the list below.');
    }

    public function revoke(Request $request, ShareLink $share)
    {
        abort_unless($request->user()->isClientAdmin() || $share->created_by === $request->user()->id, 403);
        $share->update(['revoked_at' => now()]);
        Audit::log('document.share_revoked', $share->document, 'Sharing link revoked', [], $share->client_id);

        return $this->ok('Link revoked. It no longer opens.');
    }
}

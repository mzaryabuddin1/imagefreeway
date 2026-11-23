<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\Client;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Services\PageService;
use App\Services\SigningService;
use App\Services\WorkflowEngine;
use App\Support\Audit;
use App\Support\Branding;
use App\Support\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** The page a signer opens from their email. No sign-in; the long random token is the key. */
class PublicSignController extends Controller
{
    private function resolve(string $token): array
    {
        $req = SignatureRequest::withoutGlobalScopes()->where('token', $token)->first();
        $doc = $req ? Document::withoutGlobalScopes()->find($req->document_id) : null;
        $client = $doc ? Client::find($doc->client_id) : null;
        abort_unless($req && $doc && $client?->isActive(), 404);

        return [$req, $doc, $client];
    }

    public function show(string $token)
    {
        [$req, $doc, $client] = $this->resolve($token);
        $brand = Branding::for($client);

        $this->recordView($req, $doc);
        $others = SignatureRequest::withoutGlobalScopes()->where('document_id', $doc->id)->where('status', '!=', 'cancelled')->orderBy('seq')->orderBy('id')->get(['id', 'signer_name', 'role', 'status', 'expires_at', 'seq']);
        $boxes = collect();
        $pageCount = 0;
        if ($req->status !== 'cancelled') {
            $boxes = Annotation::withoutGlobalScopes()->where('signature_request_id', $req->id)->where('kind', 'sign')->get();
            $pages = app(PageService::class);
            $pageCount = $boxes->isNotEmpty() && $pages->available($doc) ? $pages->pageCount($doc) : 0;
        }

        return view('sign.show', ['req' => $req, 'doc' => $doc, 'brand' => $brand, 'token' => $token, 'open' => $req->isOpen(), 'boxes' => $boxes, 'pageCount' => $pageCount, 'others' => $others]);
    }

    /** Counts the visit and leaves a trail entry (at most one every 10 minutes per link). */
    private function recordView(SignatureRequest $req, Document $doc): void
    {
        if ($req->status === 'cancelled') {
            return;
        }
        $fresh = ! $req->last_viewed_at || $req->last_viewed_at->lt(now()->subMinutes(10));
        $req->forceFill(['first_viewed_at' => $req->first_viewed_at ?? now(), 'last_viewed_at' => now(), 'view_count' => $req->view_count + 1])->save();
        if ($fresh) {
            Audit::log('signature.viewed', $doc, "{$req->signer_name} opened {$doc->original_name} to sign", ['ip' => request()->ip()], $doc->client_id);
        }
    }

    public function page(string $token, int $n)
    {
        [$req, $doc] = $this->resolve($token);
        abort_unless($req->status !== 'cancelled' && $n >= 1 && $n <= PageService::MAX_PAGES, 404);
        $img = app(PageService::class)->page($doc, $n);
        abort_unless($img, 404);

        return response($img[0], 200, ['Content-Type' => $img[1], 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function file(string $token)
    {
        [$req, $doc] = $this->resolve($token);
        abort_unless($req->status !== 'cancelled', 410);
        $disk = Storage::disk(config('filesystems.documents_disk'));
        abort_unless($disk->exists($doc->path), 404);

        return $disk->response($doc->path, $doc->original_name, ['Content-Type' => $doc->mime ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    public function sign(Request $request, string $token, WorkflowEngine $engine)
    {
        [$req, $doc] = $this->resolve($token);
        if (! $req->isOpen()) {
            return response()->json(['ok' => false, 'message' => 'This request is no longer open.'], 410);
        }
        $data = $request->validate([
            'typed_name' => 'required|string|max:120',
            'signature' => 'required|string|starts_with:data:image/png;base64,|max:400000',
            'consent' => 'accepted', 'method' => 'nullable|in:draw,type,upload,camera',
        ], ['consent.accepted' => 'Please tick the box to confirm you agree to sign electronically.', 'signature.required' => 'Please draw your signature.']);

        $disk = Storage::disk(config('filesystems.documents_disk'));
        $hash = $disk->exists($doc->path) ? hash('sha256', (string) $disk->get($doc->path)) : null;
        $req->update([
            'status' => 'signed', 'signed_at' => now(), 'signature' => $data['signature'], 'typed_name' => $data['typed_name'],
            'method' => $data['method'] ?? 'draw', 'doc_hash' => $hash, 'ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 300),
        ]);
        $applied = app(SigningService::class)->applyToDocument($req->fresh(), $data['signature']);
        Audit::log('signature.signed', $doc, "{$req->signer_name} signed {$doc->original_name}".($applied ? ' (signature placed in the document)' : '').' using '.($data['method'] ?? 'draw'), ['ip' => $request->ip(), 'sha256' => $hash], $doc->client_id);
        if ($req->requested_by) {
            Notice::send(\App\Models\User::find($req->requested_by), 'Document signed', "{$req->signer_name} signed {$doc->original_name}.", route('documents.show', $doc, false), 'circle-check', 'success');
        }
        app(SigningService::class)->afterSigned($req);
        $engine->signatureResult($req, 'Signed');

        return response()->json(['ok' => true, 'message' => 'Thank you. Your signature has been recorded.', 'reload' => true]);
    }

    public function decline(Request $request, string $token, WorkflowEngine $engine)
    {
        [$req, $doc] = $this->resolve($token);
        if (! $req->isOpen()) {
            return response()->json(['ok' => false, 'message' => 'This request is no longer open.'], 410);
        }
        $reason = $request->validate(['reason' => 'nullable|string|max:300'])['reason'] ?? null;
        $req->update(['status' => 'declined', 'decline_reason' => $reason, 'ip' => $request->ip()]);
        app(SigningService::class)->cancelQueued($req);
        Audit::log('signature.declined', $doc, "{$req->signer_name} declined to sign {$doc->original_name}", [], $doc->client_id);
        if ($req->requested_by) {
            Notice::send(\App\Models\User::find($req->requested_by), 'Signature declined', "{$req->signer_name} declined to sign {$doc->original_name}.", route('documents.show', $doc, false), 'circle-x', 'danger');
        }
        $engine->signatureResult($req, 'Declined');

        return response()->json(['ok' => true, 'message' => 'You have declined. The sender has been told.', 'reload' => true]);
    }
}

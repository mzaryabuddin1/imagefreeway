<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\SigningService;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Ask someone to sign a document electronically (staff side). */
class SignatureController extends Controller
{
    use Responds;

    public function store(Request $request, Document $document, SigningService $signing)
    {
        abort_unless($request->user()->canOn($document->project, 'can_download'), 403);
        $data = $request->validate([
            'signers' => 'nullable|array|max:8',
            'signers.*.name' => 'nullable|string|max:120', 'signers.*.email' => 'nullable|email|max:190',
            'signers.*.role' => 'nullable|string|max:60', 'signers.*.user_id' => 'nullable|integer',
            'recipients' => 'nullable|string|max:8000', 'recipient_role' => 'nullable|string|max:60',
            'days' => 'required|integer|min:1|max:60', 'remind_days' => 'nullable|integer|min:1|max:30',
            'message' => 'nullable|string|max:500',
        ], ['signers.*.email.email' => 'One of the signer emails is not valid.']);

        // Team members fill in their own name and email
        $signers = [];
        foreach ($data['signers'] ?? [] as $row) {
            if (! empty($row['user_id']) && ($u = User::where('id', $row['user_id'])->where('client_id', $document->client_id)->first())) {
                $row['name'] = $u->name;
                $row['email'] = $u->email;
            }
            if (blank($row['name'] ?? null) && blank($row['email'] ?? null)) {
                continue;
            }
            if (blank($row['name'] ?? null) || blank($row['email'] ?? null)) {
                return $this->fail('Each signer needs both a name and an email address.');
            }
            $signers[] = ['role' => $row['role'] ?? null, 'name' => $row['name'], 'email' => $row['email'], 'user_id' => $row['user_id'] ?? null];
        }
        $opts = ['message' => $data['message'] ?? null, 'days' => (int) $data['days'], 'in_order' => $request->boolean('in_order'), 'remind_days' => (int) ($data['remind_days'] ?? 2)];

        // Many recipients: each gets their own copy, signs first, then the fixed signers sign in order
        if (filled($data['recipients'] ?? null)) {
            $people = [];
            foreach (preg_split('/\R/', $data['recipients'], -1, PREG_SPLIT_NO_EMPTY) as $line) {
                $line = trim($line);
                if (! preg_match('/([^\s,;<>]+@[^\s,;<>]+)/', $line, $m) || ! filter_var($m[1], FILTER_VALIDATE_EMAIL)) {
                    return $this->fail("“{$line}” has no valid email address. Use one person per line: Name, email.");
                }
                $name = trim(str_replace($m[1], '', $line), " ,;<>\t");
                $people[strtolower($m[1])] = ['name' => $name ?: $m[1], 'email' => $m[1]];
            }
            abort_if(count($people) > 50, 422, 'You can send to at most 50 people at once.');
            $made = 0;
            foreach ($people as $p) {
                $copy = $signing->cloneFor($document, $p['name']);
                $signing->createPacket($copy, array_merge([['role' => $data['recipient_role'] ?? null, 'name' => $p['name'], 'email' => $p['email']]], $signers), $request->user(), ['in_order' => true] + $opts);
                $made++;
            }
            Audit::log('signature.bulk', $document, "{$document->original_name} sent for signature to {$made} people", [], $document->client_id);

            return $this->ok("{$made} separate copies were made and sent. Each person signs first, then the other signers in order.");
        }

        if (! $signers) {
            return $this->fail('Add at least one signer.');
        }
        $made = $signing->createPacket($document, $signers, $request->user(), $opts);
        $free = \App\Models\Annotation::where('document_id', $document->id)->where('kind', 'sign')->whereNull('signature_request_id')->whereNull('applied_at')->count();
        $placed = \App\Models\Annotation::where('document_id', $document->id)->where('kind', 'sign')->whereIn('signature_request_id', collect($made)->pluck('id'))->count();
        $msg = count($made) === 1 ? "Signature request sent to {$made[0]->signer_email}." : count($made).' signers added.'.($opts['in_order'] ? ' The first has been emailed; each next person is emailed when the one before has signed.' : ' All of them have been emailed.');
        $msg .= $placed ? " {$placed} signing box(es) were assigned." : '';
        $msg .= $free ? " Note: {$free} box(es) have a role nobody has, so they were not used." : '';

        return $this->ok($msg);
    }

    /** Sends a reminder now to someone who has not signed. */
    public function remind(SignatureRequest $signature, SigningService $signing)
    {
        abort_unless(auth()->user()->canOn($signature->document->project, 'can_download'), 403);
        abort_unless($signature->status === 'pending', 422, 'Only people who are being waited on can be reminded.');

        return $signing->remind($signature, true) ? $this->ok("Reminder sent to {$signature->signer_email}.") : $this->fail('The reminder could not be sent.');
    }

    public function cancel(SignatureRequest $signature)
    {
        abort_unless(in_array($signature->status, ['pending', 'queued'], true), 422, 'This request is already finished.');
        $signature->update(['status' => 'cancelled']);
        Audit::log('signature.cancelled', $signature->document, "Signature request to {$signature->signer_name} cancelled", [], $signature->client_id);

        return $this->ok('Signature request cancelled.');
    }

    public function certificate(SignatureRequest $signature)
    {
        abort_unless($signature->status === 'signed', 404);
        $signature->makeVisible('signature');

        return view('signatures.certificate', ['sig' => $signature, 'document' => $signature->document]);
    }
}

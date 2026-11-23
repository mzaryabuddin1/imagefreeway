<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\Responds;
use App\Models\Client;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Start, upgrade and stop a support view of a client's user. Every step is written to that client's audit trail. */
class SupportSessionController extends Controller
{
    use Responds;

    /** Super Admin only (route middleware). */
    public function start(Request $request, Client $client, User $user)
    {
        abort_unless($user->client_id === $client->id && ! $user->isSuperAdmin(), 404);
        abort_if($request->attributes->get('support'), 403);
        if ($user->status !== 'active' || ! $client->isActive()) {
            return $this->fail('This user or client is not active, so it cannot be viewed.');
        }
        $request->session()->put('support', ['admin_id' => $request->user()->id, 'user_id' => $user->id, 'act_until' => 0, 'ends_at' => now()->addMinutes(60)->timestamp]);
        Audit::log('support.started', $user, "Support ({$request->user()->name}) started viewing the product as {$user->name} (read-only)", [], $client->id);

        return $this->ok("You are now seeing the product as {$user->name}. Read-only.", route('dashboard'));
    }

    public function act(Request $request)
    {
        $s = $request->session()->get('support');
        abort_unless($s && $request->attributes->get('support'), 404);
        $request->session()->put('support.act_until', now()->addMinutes(30)->timestamp);
        Audit::log('support.act_enabled', $request->user(), "Support ({$request->attributes->get('support')['admin']->name}) enabled changes for 30 minutes as {$request->user()->name}", [], $request->user()->client_id);

        return $this->ok('Changes are enabled for 30 minutes. Everything you do is recorded as support.');
    }

    public function stop(Request $request)
    {
        $support = $request->attributes->get('support');
        abort_unless($support, 404);
        $user = $request->user();
        Audit::log('support.ended', $user, "Support ({$support['admin']->name}) stopped viewing as {$user->name}", [], $user->client_id);
        $request->session()->forget('support');

        return $this->ok('Back to your own account.', route('support.show', $user->client_id));
    }
}

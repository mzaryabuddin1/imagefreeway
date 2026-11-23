<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Signs out users whose account, client or domain no longer permits access. */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $reason = null;

        if ($user) {
            if ($user->status !== 'active') {
                $reason = 'Your account is no longer active.';
            } elseif (! $user->isSuperAdmin()) {
                $client = $user->client;
                $domainClient = request()->attributes->get('domainClient');
                if (! $client || ! $client->isActive()) {
                    $reason = 'Your organisation account is not active.';
                } elseif ($domainClient && $domainClient->id !== $client->id) {
                    $reason = 'This account does not belong to this site.';
                }
            }
        }

        if ($reason) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $reason, 'redirect' => route('login')], 401)
                : redirect()->route('login')->withErrors(['email' => $reason]);
        }

        return $next($request);
    }
}

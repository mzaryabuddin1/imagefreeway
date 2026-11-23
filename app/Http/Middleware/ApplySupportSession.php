<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Support console (F-OPS): a Super Admin can see the product as one of a client's users.
 * Read-only by default; "enable changes" lasts 30 minutes. The whole view ends after 60 minutes.
 * While active, the request runs as the client's user, so every permission and tenant rule applies exactly as for them.
 */
class ApplySupportSession
{
    public const ALLOWED = ['support.act', 'support.stop'];

    public function handle(Request $request, Closure $next)
    {
        $s = $request->hasSession() ? $request->session()->get('support') : null;
        $real = $s ? Auth::user() : null;

        if (! $s || ! $real) {
            return $next($request);
        }
        $target = User::withoutGlobalScopes()->find($s['user_id']);
        if (! $real->isSuperAdmin() || $real->id !== $s['admin_id'] || ! $target || $target->isSuperAdmin() || now()->timestamp > $s['ends_at']) {
            $request->session()->forget('support');

            return $next($request);
        }

        $acting = $s['act_until'] > now()->timestamp;
        Auth::setUser($target);
        $request->attributes->set('support', ['admin' => $real, 'acting' => $acting, 'act_until' => $s['act_until'], 'ends_at' => $s['ends_at']]);

        if (! $request->isMethodSafe() && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            $name = $request->route()?->getName();
            if ($name === 'logout') {
                return $this->deny($request, 'Stop the support view first, then sign out.');
            }
            if (! $acting) {
                return $this->deny($request, 'This is a read-only support view. Press "Enable changes" in the yellow bar if you need to change something.');
            }
        }

        return $next($request);
    }

    private function deny(Request $request, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => $message], 403)
            : back()->with('error', $message);
    }
}

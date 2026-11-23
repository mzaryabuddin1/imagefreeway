<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Usage: ->middleware('role:super_admin') or ('role:admin') for super + client admins. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        $allowed = match ($role) {
            'super_admin' => $user?->isSuperAdmin(),
            'admin' => $user?->isAdmin(),
            'client' => $user?->client_id !== null,           // works inside a client (documents area)
            'client_admin' => $user?->client_id !== null && $user->isClientAdmin(),
            default => false,
        };

        abort_unless($allowed, 403, 'You do not have permission to open this page.');

        return $next($request);
    }
}

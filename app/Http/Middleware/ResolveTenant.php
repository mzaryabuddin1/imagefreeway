<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;

/**
 * White-label domains: finds the client that owns the host the request came in on
 * (e.g. docs.acme.com) so the login page and branding can be that client's.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $client = Client::query()
            ->where('domain', strtolower($request->getHost()))
            ->where('status', 'active')
            ->first();

        $request->attributes->set('domainClient', $client);

        return $next($request);
    }
}

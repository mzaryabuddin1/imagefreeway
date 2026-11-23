<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the brand shown on the current screen:
 *  1. the signed-in user's client,
 *  2. otherwise the client that owns the domain the request arrived on,
 *  3. otherwise the ImageFreeway defaults.
 */
class Branding
{
    public function __construct(
        public string $name,
        public string $primary,
        public string $secondary,
        public string $logoUrl,
        public string $footer,
        public ?Client $client = null,
    ) {}

    public static function current(): self
    {
        $client = Auth::user()?->client ?? request()->attributes->get('domainClient');

        return self::for($client);
    }

    public static function for(?Client $client): self
    {
        return new self(
            name: $client?->app_name ?: ($client?->name ?? 'ImageFreeway'),
            primary: $client?->primary_color ?: Client::DEFAULT_PRIMARY,
            secondary: $client?->secondary_color ?: Client::DEFAULT_SECONDARY,
            logoUrl: $client?->logo_path
                ? route('branding.logo', $client).'?v='.substr(md5($client->logo_path), 0, 8)
                : asset('images/imagefreeway-logo.svg'),
            footer: $client?->footer_text ?: ($client ? '© '.date('Y').' '.$client->name : '© '.date('Y').' ImageFreeway. All rights reserved.'),
            client: $client,
        );
    }
}

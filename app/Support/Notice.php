<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SystemNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class Notice
{
    /** @param User|Collection|array $to */
    public static function send($to, string $title, string $message, ?string $url = null, string $icon = 'bell', string $level = 'info'): void
    {
        Notification::send($to, new SystemNotice($title, $message, $url, $icon, $level));
    }

    /** All active super admins. */
    public static function superAdmins(): Collection
    {
        return User::where('role', 'super_admin')->where('status', 'active')->get();
    }

    /** Active client admins of one client. */
    public static function clientAdmins(int $clientId): Collection
    {
        return User::where('client_id', $clientId)->where('role', 'client_admin')->where('status', 'active')->get();
    }
}

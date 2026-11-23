<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public static function log(string $action, ?Model $subject = null, ?string $description = null, array $meta = [], ?int $clientId = null): void
    {
        $user = Auth::user();

        // A Super Admin acting through the support console is marked in the client's trail (F-OPS support console)
        $support = request()->attributes->get('support');
        if ($support && ! str_starts_with($action, 'support.')) {
            $description = '[Support: '.$support['admin']->name.'] '.$description;
            $meta['support_admin_id'] = $support['admin']->id;
        }

        AuditLog::withoutGlobalScopes()->create([
            'client_id' => $clientId ?? $user?->client_id ?? ($subject?->client_id ?? null),
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
        ]);
    }
}

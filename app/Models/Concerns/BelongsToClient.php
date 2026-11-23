<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant isolation at the model layer. Add this trait to every model that holds
 * client data (documents, projects, ...). Non-super-admins can only ever read or
 * create rows for their own client, whatever filter or id they send.
 */
trait BelongsToClient
{
    protected static function bootBelongsToClient(): void
    {
        static::addGlobalScope('client', function (Builder $query) {
            $user = Auth::user();
            if ($user && ! $user->isSuperAdmin()) {
                $query->where($query->getModel()->getTable().'.client_id', $user->client_id);
            }
        });

        static::creating(function ($model) {
            $user = Auth::user();
            if ($user && ! $user->isSuperAdmin()) {
                $model->client_id = $user->client_id;
            }
        });
    }
}

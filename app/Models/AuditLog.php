<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToClient;

    protected $fillable = ['client_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'meta', 'ip'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }
}

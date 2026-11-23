<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLink extends Model
{
    use BelongsToClient;

    protected $fillable = ['client_id', 'document_id', 'created_by', 'token', 'code_hash', 'allow_download', 'expires_at', 'revoked_at', 'opened_count', 'download_count', 'last_opened_at'];

    protected function casts(): array
    {
        return ['allow_download' => 'boolean', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_opened_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return $this->revoked_at ? 'Revoked' : ($this->expires_at->isPast() ? 'Expired' : 'Active');
    }
}

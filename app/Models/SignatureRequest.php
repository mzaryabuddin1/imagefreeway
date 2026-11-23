<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureRequest extends Model
{
    use BelongsToClient;

    protected $fillable = [
        'client_id', 'document_id', 'item_id', 'requested_by', 'node_id', 'signer_name', 'signer_email', 'message', 'token', 'status',
        'expires_at', 'signed_at', 'signature', 'typed_name', 'doc_hash', 'ip', 'user_agent', 'decline_reason',
        'role', 'seq', 'signer_user_id', 'activated_at', 'first_viewed_at', 'last_viewed_at', 'view_count', 'reminded_at', 'reminder_count', 'remind_after_days', 'method',
    ];

    protected $hidden = ['signature'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'signed_at' => 'datetime', 'activated_at' => 'datetime', 'first_viewed_at' => 'datetime', 'last_viewed_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    public function isOpen(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'signed' => 'Signed',
            $this->status === 'declined' => 'Declined',
            $this->status === 'cancelled' => 'Cancelled',
            $this->status === 'queued' => 'Not sent yet',
            $this->expires_at->isPast() => 'Expired',
            default => 'Waiting',
        };
    }
}

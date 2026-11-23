<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowEvent extends Model
{
    use BelongsToClient;

    public $timestamps = false;

    protected $fillable = ['client_id', 'item_id', 'node_id', 'node_name', 'outcome', 'user_id', 'action', 'comment'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file that failed the virus scan. It is stored apart, never filed, until an admin releases or destroys it. */
class QuarantinedFile extends Model
{
    use BelongsToClient;

    protected $fillable = ['client_id', 'project_id', 'user_id', 'original_name', 'mime', 'size', 'path', 'source', 'source_detail', 'threat', 'status', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}

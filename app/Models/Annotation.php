<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A mark drawn on a page. x, y, w, h are fractions (0 to 1) of the page, so they fit any zoom. */
class Annotation extends Model
{
    use BelongsToClient;

    protected $fillable = ['client_id', 'document_id', 'signature_request_id', 'user_id', 'kind', 'page', 'x', 'y', 'w', 'h', 'text', 'applied_at'];

    protected function casts(): array
    {
        return ['x' => 'float', 'y' => 'float', 'w' => 'float', 'h' => 'float', 'applied_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}

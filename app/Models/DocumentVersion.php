<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An earlier file of a document. The document row always holds the current file. */
class DocumentVersion extends Model
{
    use BelongsToClient;

    public $timestamps = false;

    protected $fillable = ['client_id', 'document_id', 'version_no', 'path', 'mime', 'size', 'uploaded_by', 'note', 'replaced_at'];

    protected function casts(): array
    {
        return ['replaced_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}

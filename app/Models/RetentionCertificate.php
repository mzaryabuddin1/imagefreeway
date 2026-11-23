<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionCertificate extends Model
{
    use BelongsToClient;

    public $timestamps = false;

    protected $fillable = ['client_id', 'project_id', 'approved_by', 'project_name', 'document_name', 'document_id', 'index_values', 'disposal_date', 'destroyed_at'];

    protected function casts(): array
    {
        return ['index_values' => 'array', 'disposal_date' => 'date', 'destroyed_at' => 'datetime'];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}

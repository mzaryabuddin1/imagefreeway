<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToClient, SoftDeletes;

    protected $fillable = ['client_id', 'department_id', 'name', 'description', 'ocr_enabled', 'retention_years', 'retention_field_id', 'link_field', 'naming_pattern', 'sort'];

    protected function casts(): array
    {
        return ['ocr_enabled' => 'boolean'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ProjectField::class)->orderBy('sort')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function rights(): HasMany
    {
        return $this->hasMany(ProjectRight::class);
    }
}

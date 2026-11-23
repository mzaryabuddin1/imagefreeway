<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToClient, SoftDeletes;

    protected $fillable = ['client_id', 'name', 'sort'];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('sort')->orderBy('name');
    }
}

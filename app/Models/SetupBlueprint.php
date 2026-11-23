<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A named, versioned client setup (departments, projects, fields, document types, forms, workflows, modules). Not tied to a tenant. */
class SetupBlueprint extends Model
{
    protected $table = 'blueprints';

    protected $fillable = ['name', 'industry', 'version', 'notes', 'data', 'source_client_id', 'created_by'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function label(): string
    {
        return "{$this->name} (v{$this->version})";
    }
}

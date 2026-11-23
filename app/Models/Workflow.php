<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use BelongsToClient, SoftDeletes;

    protected $fillable = ['client_id', 'project_id', 'name', 'description', 'graph', 'auto_start', 'active'];

    protected function casts(): array
    {
        return ['graph' => 'array', 'auto_start' => 'boolean', 'active' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkflowItem::class);
    }

    public function node(?string $id): ?array
    {
        foreach ($this->graph['nodes'] ?? [] as $n) {
            if ($n['id'] === $id) {
                return $n;
            }
        }

        return null;
    }

    public function startNode(): ?array
    {
        foreach ($this->graph['nodes'] ?? [] as $n) {
            if ($n['type'] === 'start') {
                return $n;
            }
        }

        return null;
    }

    /** id of the node a port leads to, if connected. */
    public function next(string $from, string $port): ?string
    {
        foreach ($this->graph['edges'] ?? [] as $e) {
            if ($e['from'] === $from && $e['port'] === $port) {
                return $e['to'];
            }
        }

        return null;
    }
}

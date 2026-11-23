<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowItem extends Model
{
    use BelongsToClient;

    protected $fillable = ['client_id', 'workflow_id', 'document_id', 'node_id', 'data', 'result', 'started_by', 'status', 'stage_entered_at', 'escalation_level', 'completed_at'];

    protected function casts(): array
    {
        return ['stage_entered_at' => 'datetime', 'completed_at' => 'datetime', 'data' => 'array'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class)->withTrashed();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkflowEvent::class, 'item_id')->orderBy('id');
    }

    /** The node this item is waiting at (a graph array) or null. */
    public function node(): ?array
    {
        return $this->node_id ? $this->workflow?->node($this->node_id) : null;
    }

    public function nodeName(): string
    {
        return $this->node()['name'] ?? 'Finished';
    }

    /** Is this person allowed to act at the current task? Empty assignee list = Client Admins. */
    public function isAssignedTo(User $user): bool
    {
        $node = $this->node();
        if (! $node || $node['type'] !== 'task' || $user->client_id !== $this->client_id) {
            return false;
        }
        $ids = array_map('intval', $node['config']['assignees'] ?? []);

        return $ids ? in_array($user->id, $ids, true) || $user->isClientAdmin() : $user->isClientAdmin();
    }

    public function isOverdue(): bool
    {
        $hours = $this->node()['config']['overdue_hours'] ?? null;
        $since = ! empty($this->data['esc_at']) ? \Illuminate\Support\Carbon::parse($this->data['esc_at']) : $this->stage_entered_at;

        return $this->status === 'active' && $hours && $since && $since->copy()->addHours((int) $hours)->isPast();
    }

    /** Active items whose current task is assigned to this user (and whose project they can view). */
    public static function waitingFor($user)
    {
        return static::where('status', 'active')->whereNotNull('node_id')
            ->with(['workflow', 'document.project'])->oldest('stage_entered_at')->get()
            ->filter(fn ($i) => $i->document && $i->isAssignedTo($user) && $user->canOn($i->document->project, 'can_view'))
            ->values();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use BelongsToClient;

    public const FREQUENCIES = ['daily' => 'Every day', 'weekly' => 'Every Monday', 'monthly' => 'On the 1st of each month'];

    protected $fillable = ['client_id', 'project_id', 'created_by', 'name', 'frequency', 'group_by', 'sum_by', 'recipients', 'active', 'last_sent_at'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_sent_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return string[] */
    public function emails(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $this->recipients)), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    public function isDueOn(\Carbon\CarbonInterface $day): bool
    {
        if (! $this->active || $this->last_sent_at?->isSameDay($day)) {
            return false;
        }

        return match ($this->frequency) {
            'daily' => true,
            'weekly' => $day->isMonday(),
            'monthly' => $day->day === 1,
            default => false,
        };
    }
}

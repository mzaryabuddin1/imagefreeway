<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToClient, SoftDeletes;

    protected $fillable = [
        'client_id', 'project_id', 'uploaded_by', 'indexed_by', 'deleted_by', 'original_name', 'mime', 'size', 'path',
        'status', 'index_values', 'search_text', 'ocr_text', 'ocr_status', 'indexed_at',
        'disposal_date', 'legal_hold', 'legal_hold_reason', 'doc_type', 'email_from',
    ];

    protected function casts(): array
    {
        return ['index_values' => 'array', 'indexed_at' => 'datetime', 'disposal_date' => 'date', 'legal_hold' => 'boolean'];
    }

    /** Retention clock (F-COMP-01): start date + project's retention years. Start = chosen date field, else upload date. */
    public function recomputeDisposal(?Project $project = null): void
    {
        $project ??= $this->project;
        if (! $project?->retention_years) {
            $this->disposal_date = null;

            return;
        }
        $start = null;
        if ($project->retention_field_id) {
            $key = ProjectField::withTrashed()->whereKey($project->retention_field_id)->value('key');
            $raw = $key ? ($this->index_values[$key] ?? null) : null;
            $start = $raw ? \Illuminate\Support\Carbon::parse($raw) : null;
        } else {
            $start = $this->created_at;
        }
        $this->disposal_date = $start?->copy()->addYears((int) $project->retention_years)->toDateString();
    }

    /** Fills calculated fields from their formulas (arithmetic only, see Formula). */
    public function applyCalculated(?Project $project = null): void
    {
        $project ??= $this->project;
        $vals = $this->index_values ?? [];
        foreach ($project?->fields ?? [] as $f) {
            if ($f->type === 'calculated' && $f->formula) {
                $vals[$f->key] = \App\Support\Formula::evaluate($f->formula, $vals);
            }
        }
        $this->index_values = $vals;
    }

    /** Renames the document from the project's pattern, e.g. "{contract_number}-{invoice_number}". Keeps the name if any part is empty. */
    public function applyNaming(?Project $project = null): void
    {
        $project ??= $this->project;
        $pattern = $project?->naming_pattern;
        if (! $pattern) {
            return;
        }
        $empty = false;
        $name = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function ($m) use (&$empty) {
            $v = match ($m[1]) {
                'doc_type' => $this->doc_type, 'today' => today()->toDateString(), 'id' => (string) $this->id,
                default => $this->index_values[$m[1]] ?? null,
            };
            $empty = $empty || $v === null || $v === '';

            return (string) $v;
        }, $pattern);
        $name = trim(preg_replace('/[^\pL\pN\s._()\-&]+/u', '', $name));
        if ($empty || $name === '') {
            return;
        }
        $ext = pathinfo($this->original_name, PATHINFO_EXTENSION);
        $this->original_name = \Illuminate\Support\Str::limit($name, 150, '').($ext ? ".{$ext}" : '');
    }

    /** Every document that shares this one's linking value (e.g. the same contract number), itself included. */
    public function linked(): \Illuminate\Support\Collection
    {
        $key = $this->project?->link_field;
        $val = $key ? ($this->index_values[$key] ?? null) : null;
        if (! $key || ! $val) {
            return collect([$this]);
        }

        return static::where('client_id', $this->client_id)->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(index_values, ?)) = ?', ['$."'.str_replace('"', '', $key).'"', (string) $val])->orderBy('id')->get();
    }

    public function workflowItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkflowItem::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf' || str_ends_with(strtolower($this->original_name), '.pdf');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/') && ! str_contains($this->mime, 'tiff');
    }

    public function humanSize(): string
    {
        $b = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $u) {
            if ($b < 1024 || $u === 'GB') {
                return round($b, $u === 'B' ? 0 : 1).' '.$u;
            }
            $b /= 1024;
        }
    }

    /** Rebuilds the flattened text used by global search from the index values. */
    public function refreshSearchText(): void
    {
        $this->search_text = collect($this->index_values ?? [])->flatten()->filter(fn ($v) => $v !== null && $v !== '')->implode(' ');
    }
}

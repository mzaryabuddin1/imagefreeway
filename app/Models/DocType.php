<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocType extends Model
{
    use BelongsToClient;

    public const DEFAULT_PATTERN = '/([A-Z]{1,5}-?\d{3,8}|\d{4,10})/i';

    protected $fillable = ['client_id', 'name', 'email_alias', 'project_id', 'workflow_id', 'index_field', 'subject_pattern'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class)->withTrashed();
    }

    /** The first number in an email subject, using this type's pattern or the built-in one. Null when nothing matches. */
    public function extractNumber(string $subject): ?string
    {
        $pattern = $this->subject_pattern ?: self::DEFAULT_PATTERN;
        if (@preg_match($pattern, '') === false) {
            $pattern = self::DEFAULT_PATTERN;
        }

        return preg_match($pattern, $subject, $m) ? ($m[1] ?? $m[0]) : null;
    }
}

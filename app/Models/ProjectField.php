<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectField extends Model
{
    use BelongsToClient, SoftDeletes;

    public const TYPES = [
        'text' => 'Text',
        'number' => 'Whole number',
        'amount' => 'Amount',
        'date' => 'Date',
        'yesno' => 'Yes / No',
        'picklist' => 'Pick-list',
        'calculated' => 'Calculated',
    ];

    protected $fillable = [
        'client_id', 'project_id', 'label', 'key', 'type', 'required', 'searchable',
        'show_in_results', 'read_only', 'options', 'formula', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean', 'searchable' => 'boolean', 'show_in_results' => 'boolean',
            'read_only' => 'boolean', 'options' => 'array',
        ];
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}

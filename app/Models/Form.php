<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An eForm: a list of fields filled in on screen (text, date, tick box, pick-list, signature...). */
class Form extends Model
{
    use BelongsToClient, SoftDeletes;

    public const TYPES = ['text' => 'Short text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'checkbox' => 'Tick box', 'select' => 'Pick-list', 'signature' => 'Signature'];

    protected $fillable = ['client_id', 'name', 'description', 'fields', 'active'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'active' => 'boolean'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** Laravel validation rules for a submission, keyed form.{id}.{key}. */
    public function rulesFor(string $prefix): array
    {
        $rules = [];
        foreach ($this->fields ?? [] as $f) {
            $r = [($f['required'] ?? false) ? 'required' : 'nullable'];
            $r = array_merge($r, match ($f['type'] ?? 'text') {
                'number' => ['numeric'],
                'date' => ['date'],
                'checkbox' => ['boolean'],
                'select' => [\Illuminate\Validation\Rule::in($f['options'] ?? [])],
                'signature' => ['string', 'starts_with:data:image/png;base64,', 'max:400000'],
                'textarea' => ['string', 'max:3000'],
                default => ['string', 'max:500'],
            });
            $rules["{$prefix}.{$f['key']}"] = $r;
        }

        return $rules;
    }
}

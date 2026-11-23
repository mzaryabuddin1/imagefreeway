<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    public const MODULES = [
        'intelligence' => ['Intelligence', 'AI extraction and auto-filing of documents'],
        'crm' => ['CRM and Contacts', 'Vendor, patient and applicant records linked to documents'],
        'email_automation' => ['Email Automation', 'Timed email sequences driven by document events'],
        'calling' => ['Calling', 'Click-to-call and call logging from the viewer'],
    ];

    public const DEFAULT_PRIMARY = '#78a62a';   // ImageFreeway green
    public const DEFAULT_SECONDARY = '#2b3990'; // ImageFreeway navy

    protected $fillable = [
        'name', 'slug', 'domain', 'status', 'app_name', 'primary_color', 'secondary_color',
        'logo_path', 'footer_text', 'modules', 'settings', 'blueprint_id',
    ];

    protected function casts(): array
    {
        return ['modules' => 'array', 'settings' => 'array'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasModule(string $key): bool
    {
        return (bool) (($this->modules ?? [])[$key] ?? false);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

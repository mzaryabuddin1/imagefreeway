<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLES = [
        'super_admin' => 'Super Admin',
        'client_admin' => 'Client Admin',
        'user' => 'User',
    ];

    protected $fillable = [
        'client_id', 'name', 'email', 'phone', 'password', 'role', 'status', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isClientAdmin(): bool
    {
        return $this->role === 'client_admin';
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isClientAdmin();
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** @var array<int, ProjectRight|null> */
    private array $rightCache = [];

    /** Does this user hold $right (can_view, can_index, ...) on the project? Client admins hold every right in their client. */
    public function canOn(Project $project, string $right = 'can_view'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if ($project->client_id !== $this->client_id) {
            return false;
        }
        if ($this->isClientAdmin()) {
            return true;
        }
        $this->rightCache[$project->id] ??= ProjectRight::where('project_id', $project->id)->where('user_id', $this->id)->first() ?? false;

        return (bool) ($this->rightCache[$project->id]->{$right} ?? false);
    }

    /** Projects (as a query) in which this user holds $right. Always limited to the user's own client. */
    public function projectsWith(string $right = 'can_view')
    {
        $q = Project::query()->with('department');
        if ($this->isSuperAdmin() || $this->isClientAdmin()) {
            return $q;
        }

        return $q->whereIn('id', ProjectRight::where('user_id', $this->id)->where($right, true)->pluck('project_id'));
    }

    /** Tenant isolation: super admins see everyone, everyone else only their own client. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $viewer->isSuperAdmin() ? $query : $query->where('client_id', $viewer->client_id);
    }
}

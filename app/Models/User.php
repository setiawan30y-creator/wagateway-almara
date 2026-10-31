<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pengguna panel. Role: owner | admin | agent.
 * @2026-Alifiandra
 */
class User extends Authenticatable
{
    use \App\Models\Concerns\BelongsToTenant;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'agent' => 'Agent (CS)',
    ];

    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role', 'is_active', 'is_superadmin', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'role' => 'admin',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_superadmin' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}

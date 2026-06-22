<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AdminPermissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'is_active',
    'custom_permissions',
    'mfa_enabled',
    'failed_login_attempts',
    'locked_until',
    'last_login_at',
    'last_login_ip',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = AdminPermissions::ROLE_SUPER_ADMIN;
    public const ROLE_SUPER_ADMIN = AdminPermissions::ROLE_SUPER_ADMIN;
    public const ROLE_SUB_ADMIN = AdminPermissions::ROLE_SUB_ADMIN;
    public const ROLE_CUSTOM = AdminPermissions::ROLE_CUSTOM;
    public const ROLE_USER = 'user';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'custom_permissions' => 'array',
            'mfa_enabled' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return AdminPermissions::isAdminRole($this->role);
    }

    public function normalizedRole(): string
    {
        return AdminPermissions::normalizeRole($this->role);
    }

    public function permissions(): array
    {
        $permissions = array_merge(
            AdminPermissions::defaultsForRole($this->role),
            array_values(array_filter($this->custom_permissions ?? []))
        );

        return array_values(array_unique($permissions));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function requiresMfa(): bool
    {
        return $this->mfa_enabled || in_array($this->normalizedRole(), config('admin.mfa.required_roles', []), true);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}

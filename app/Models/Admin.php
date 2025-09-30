<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;

class Admin extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles, HasPermissions;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'permissions',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'last_login_at' => 'datetime',
    ];

    // Filament User Implementation
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'active';
    }

    // Override guard name for Spatie permissions
    protected $guard_name = 'admin';

    public function getGuardName(): string
    {
        return 'admin';
    }

    // Custom method to safely check permissions using direct database query
    public function safeHasPermission(string $permission): bool
    {
        // Check via database query directly to avoid Spatie Permission null errors
        return \DB::table('role_has_permissions')
            ->join('model_has_roles', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_roles.model_type', get_class($this))
            ->where('model_has_roles.model_id', $this->id)
            ->where('permissions.name', $permission)
            ->where('permissions.guard_name', 'admin')
            ->exists();
    }


    // Role Checking Methods
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function canManageTrains(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'operator']);
    }

    public function canViewOnly(): bool
    {
        return $this->role === 'viewer';
    }

    // Update login tracking
    public function updateLastLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }
}
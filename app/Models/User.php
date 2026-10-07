<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'status', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'last_login_at' => 'datetime',
        ];
    }

    protected ?array $cachedRoleSlugs = null;

    protected ?array $cachedPermissionSlugs = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function roleSlugs(): array
    {
        return $this->cachedRoleSlugs ??= $this->roles()->pluck('slug')->all();
    }

    public function permissionSlugs(): array
    {
        if ($this->cachedPermissionSlugs === null) {
            $this->cachedPermissionSlugs = $this->roles()
                ->with('permissions:id,slug')
                ->get()
                ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
                ->unique()
                ->values()
                ->all();
        }

        return $this->cachedPermissionSlugs;
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roleSlugs(), true);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return in_array($slug, $this->permissionSlugs(), true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

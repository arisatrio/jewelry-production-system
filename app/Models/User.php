<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $user_id
 * @property string $email
 * @property int|null $role_id
 * @property string|null $spk_role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $legacy_password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role|null $role
 */
#[Fillable(['name', 'user_id', 'email', 'password', 'role_id', 'spk_role'])]
#[Hidden(['password', 'legacy_password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function hasPermission(string $permission): bool
    {
        $role = $this->role;

        if ($role === null) {
            return false;
        }

        if (! $role->relationLoaded('permissions')) {
            $role->load('permissions');
        }

        return $role->hasPermission($permission);
    }

    public function matchesPassword(string $plainPassword): bool
    {
        $storedPassword = $this->attributes['password'] ?? null;

        if (is_string($storedPassword) && $storedPassword !== '' && Hash::check($plainPassword, $storedPassword)) {
            return true;
        }

        $legacyPassword = $this->attributes['legacy_password'] ?? null;

        if (! is_string($legacyPassword) || $legacyPassword === '') {
            return false;
        }

        $legacyMatches = password_verify($plainPassword, $legacyPassword)
            || hash_equals($legacyPassword, md5($plainPassword));

        if (! $legacyMatches) {
            return false;
        }

        if ($this->exists && Schema::hasColumn($this->getTable(), 'legacy_password')) {
            $this->forceFill([
                'password' => $plainPassword,
                'legacy_password' => null,
            ])->save();
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $role = $this->role;

        if ($role === null) {
            return [];
        }

        if (! $role->relationLoaded('permissions')) {
            $role->load('permissions');
        }

        return $role->permissions
            ->filter(fn (Permission $permission): bool => (int) $permission->is_active === 1)
            ->pluck('name')
            ->values()
            ->all();
    }
}

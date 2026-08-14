<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $is_deleted
 * @property string|null $created_by
 * @property string|null $modified_by
 */
class Role extends Model
{
    protected $table = 'role';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_deleted',
        'created_by',
        'modified_by',
    ];

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id',
        )->withTimestamps();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains(
            fn (Permission $item): bool => $item->name === $permission && (int) $item->is_active === 1,
        );
    }
}

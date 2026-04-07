<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'LGL_ROLE';

    protected $primaryKey = 'ROLE_ID'; // Migration renamed 'id' to 'ROLE_ID'

    const CREATED_AT = 'REF_ROLE_CREATED_DT';

    const UPDATED_AT = 'REF_ROLE_UPDATED_DT';

    protected $fillable = [
        'ROLE_NAME',
        'ROLE_SLUG',
        'GUARD_NAME',
        'ROLE_DESCRIPTION',
        'IS_ACTIVE',
        'REF_ROLE_CREATED_BY',
        'REF_ROLE_UPDATED_BY',
    ];

    protected function casts(): array
    {
        return [
            'ROLE_ID' => 'integer',
            'IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get the users for this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'USER_ROLE_ID');
    }

    /**
     * Get the permissions for this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'LGL_ROLE_PERMISSION', 'ROLE_ID', 'PERMISSION_ID');
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->whereRaw('LOWER("PERMISSION_CODE") = ?', [strtolower($slug)])->exists();
    }

    /**
     * Give a permission to this role.
     */
    public function givePermission(Permission|int|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::whereRaw('LOWER("PERMISSION_CODE") = ?', [strtolower($permission)])->firstOrFail();
        }

        if (is_int($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        $this->permissions()->syncWithoutDetaching([$permission->LGL_ROW_ID]);
    }

    /**
     * Revoke a permission from this role.
     */
    public function revokePermission(Permission|int|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::whereRaw('LOWER("PERMISSION_CODE") = ?', [strtolower($permission)])->firstOrFail();
        }

        if (is_int($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        $this->permissions()->detach($permission->LGL_ROW_ID);
    }

    /**
     * Sync permissions for this role.
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Determine if the role is a built-in system role.
     */
    public function getIsSystemAttribute(): bool
    {
        return in_array(strtolower($this->ROLE_SLUG), ['super-admin', 'legal', 'pic', 'management']);
    }
}

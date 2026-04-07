<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'LGL_PERMISSION';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_PERM_CREATED_DT';

    const UPDATED_AT = 'REF_PERM_UPDATED_DT';

    protected $fillable = [
        'PERMISSION_ID',
        'PERMISSION_NAME',
        'PERMISSION_CODE',
        'PERMISSION_GROUP',
        'PERMISSION_DESC',
        'GUARD_NAME',
        'IS_ACTIVE',
        'REF_PERM_CREATED_BY',
        'REF_PERM_UPDATED_BY',
    ];

    protected function casts(): array
    {
        return [
            'LGL_ROW_ID' => 'integer',
            'IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'LGL_ROLE_PERMISSION', 'PERMISSION_ID', 'ROLE_ID');
    }

    /**
     * Scope to only active permissions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('IS_ACTIVE', 1);
    }

    /**
     * Scope to filter by group.
     */
    public function scopeByGroup(Builder $query, string $group): Builder
    {
        return $query->where('PERMISSION_GROUP', $group);
    }

    /**
     * Get all active permissions grouped by PERMISSION_GROUP.
     *
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Permission>>
     */
    public static function allGrouped(): Collection
    {
        return static::query()
            ->active()
            ->orderBy('PERMISSION_GROUP')
            ->orderBy('PERMISSION_NAME')
            ->get()
            ->groupBy('PERMISSION_GROUP');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    protected $table = 'LGL_DIVISION';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_DIV_CREATED_DT';

    const UPDATED_AT = 'REF_DIV_UPDATED_DT';

    protected $fillable = [
        'REF_DIV_NAME',
        'REF_DIV_ID',
        'REF_DIV_DESC',
        'IS_ACTIVE',
    ];

    protected function casts(): array
    {
        return [
            'LGL_ROW_ID' => 'integer',
            'IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get the division code.
     */
    public function getCodeAttribute(): string
    {
        return $this->REF_DIV_ID;
    }

    /**
     * Get the users in this division.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'DIV_ID');
    }

    /**
     * Get the contracts for this division.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'CONTR_DIV_ID');
    }

    /**
     * Get the departments for this division.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'DIV_ID');
    }

    /**
     * Get the tickets for this division.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'DIV_ID');
    }

    /**
     * Scope to filter only active divisions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('IS_ACTIVE', true);
    }
}

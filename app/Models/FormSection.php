<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSection extends Model
{
    protected $table = 'LGL_FORM_SECTION';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'SECT_CREATED_DT';

    const UPDATED_AT = 'SECT_UPDATED_DT';

    protected $fillable = [
        'SECT_CODE',
        'SECT_LABEL',
        'SECT_DESCRIPTION',
        'SECT_SORT_ORDER',
        'SECT_IS_ACTIVE',
        'SECT_SHOW_ON_CREATE',
        'SECT_SHOW_ON_DETAIL',
    ];

    protected function casts(): array
    {
        return [
            'SECT_IS_ACTIVE' => 'boolean',
            'SECT_SHOW_ON_CREATE' => 'boolean',
            'SECT_SHOW_ON_DETAIL' => 'boolean',
        ];
    }

    /**
     * Get all questions belonging to this section.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class, 'QUEST_SECTION', 'SECT_CODE');
    }

    /**
     * Scope: only active sections.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('SECT_IS_ACTIVE', true);
    }

    /**
     * Scope: sections visible on create form.
     */
    public function scopeForCreate(Builder $query): Builder
    {
        return $query->where('SECT_SHOW_ON_CREATE', true);
    }

    /**
     * Scope: sections visible on detail page.
     */
    public function scopeForDetail(Builder $query): Builder
    {
        return $query->where('SECT_SHOW_ON_DETAIL', true);
    }

    /**
     * Scope: ordered by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('SECT_SORT_ORDER');
    }
}

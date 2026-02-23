<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormQuestion extends Model
{
    protected $table = 'LGL_FORM_QUESTION';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'QUEST_CREATED_DT';

    const UPDATED_AT = 'QUEST_UPDATED_DT';

    protected $fillable = [
        'QUEST_DOC_TYPE_ID',
        'QUEST_SECTION',
        'QUEST_CODE',
        'QUEST_LABEL',
        'QUEST_TYPE',
        'QUEST_OPTIONS',
        'QUEST_WIDTH',
        'QUEST_IS_REQUIRED',
        'QUEST_SORT_ORDER',
        'QUEST_IS_ACTIVE',
        'QUEST_DEPENDS_ON',
        'QUEST_DEPENDS_VALUE',
        'QUEST_PLACEHOLDER',
        'QUEST_DESCRIPTION',
        'QUEST_MAX_SIZE_KB',
        'QUEST_ACCEPT',
        'QUEST_IS_MULTIPLE',
    ];

    protected function casts(): array
    {
        return [
            'QUEST_OPTIONS' => 'array',
            'QUEST_IS_REQUIRED' => 'boolean',
            'QUEST_IS_ACTIVE' => 'boolean',
            'QUEST_IS_MULTIPLE' => 'boolean',
        ];
    }

    /**
     * Get the document type this question belongs to.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'QUEST_DOC_TYPE_ID');
    }

    /**
     * Get all answers for this question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(TicketAnswer::class, 'ANS_QUESTION_ID');
    }

    /**
     * Scope: only active questions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('QUEST_IS_ACTIVE', true);
    }

    /**
     * Scope: filter by section.
     */
    public function scopeForSection(Builder $query, string $section): Builder
    {
        return $query->where('QUEST_SECTION', $section);
    }

    /**
     * Scope: filter by document type ID (includes questions with null doc type = all types).
     */
    public function scopeForDocType(Builder $query, ?int $docTypeId): Builder
    {
        return $query->where(function ($q) use ($docTypeId) {
            $q->whereNull('QUEST_DOC_TYPE_ID');
            if ($docTypeId) {
                $q->orWhere('QUEST_DOC_TYPE_ID', $docTypeId);
            }
        });
    }

    /**
     * Scope: ordered by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('QUEST_SORT_ORDER');
    }
}

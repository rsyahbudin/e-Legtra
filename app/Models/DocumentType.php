<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $table = 'LGL_DOC_TYPE_MASTER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_DOC_TYPE_CREATED_DT';

    const UPDATED_AT = 'REF_DOC_TYPE_UPDATED_DT';

    protected $fillable = [
        'code',
        'REF_DOC_TYPE_NAME',
        'description',
        'requires_contract',
        'REF_DOC_TYPE_IS_ACTIVE',
        'DOC_TYPE_SORT_ORDER',
    ];

    protected function casts(): array
    {
        return [
            'requires_contract' => 'boolean',
            'REF_DOC_TYPE_IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get the document type name.
     */
    public function getNameAttribute(): string
    {
        return $this->REF_DOC_TYPE_NAME;
    }

    /**
     * Get document type ID by code (cached).
     */
    public static function getIdByCode(string $code): ?int
    {
        return cache()->remember("document_type_{$code}", 3600, function () use ($code) {
            return static::where('code', $code)->value('LGL_ROW_ID');
        });
    }

    /**
     * Get tickets with this document type.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'TCKT_DOC_TYPE_ID');
    }

    /**
     * Get contracts with this document type.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'CONTR_DOC_TYPE_ID');
    }

    /**
     * Scope to only get active document types.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('REF_DOC_TYPE_IS_ACTIVE', true)
            ->orderBy('DOC_TYPE_SORT_ORDER', 'asc')
            ->orderBy('REF_DOC_TYPE_NAME', 'asc');
    }

    /**
     * Check if this document type requires contract creation.
     */
    public function requiresContract(): bool
    {
        return $this->requires_contract;
    }
}

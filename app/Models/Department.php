<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $table = 'LGL_DEPARTMENT';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_DEPT_CREATED_DT';

    const UPDATED_AT = 'REF_DEPT_UPDATED_DT';

    protected $fillable = [
        'DIV_ID',
        'REF_DEPT_NAME',
        'REF_DEPT_ID',
        'email',
        'cc_emails',
        'IS_ACTIVE',
    ];

    protected function casts(): array
    {
        return [
            'cc_emails' => 'array',
            'IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get cc_emails as array (handles null and string cases).
     */
    public function getCcEmailsListAttribute(): array
    {
        if (is_null($this->cc_emails)) {
            return [];
        }

        // If it's already an array (from cast), return it
        if (is_array($this->cc_emails)) {
            return $this->cc_emails;
        }

        // If it's a string, try to parse it
        if (is_string($this->cc_emails)) {
            // Remove any surrounding quotes and decode
            $cleaned = trim($this->cc_emails, '"');

            // Try JSON decode first
            $decoded = json_decode($cleaned, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // If not JSON, try comma-separated
            if (str_contains($cleaned, ',')) {
                $emails = array_map('trim', explode(',', $cleaned));

                return array_filter($emails, function ($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }

            // Single email
            if (filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                return [$cleaned];
            }
        }

        return [];
    }

    /**
     * Get the division that owns the department.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'DIV_ID');
    }

    /**
     * Get the contracts for this department.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'CONTR_DEPT_ID');
    }

    /**
     * Get the users for this department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'DEPT_ID');
    }

    /**
     * Get Legal department email.
     */
    public static function getLegalEmail(): ?string
    {
        $legalDept = static::where('REF_DEPT_ID', 'LEGAL')
            ->orWhere('REF_DEPT_NAME', 'LIKE', '%Legal%')
            ->first();

        return $legalDept?->email;
    }

    /**
     * Scope to filter only active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }
}

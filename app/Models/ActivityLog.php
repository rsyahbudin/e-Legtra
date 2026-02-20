<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'LGL_USER_ADTRL_LOG';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_CONTR_CREATED_DT';

    const UPDATED_AT = 'REF_CONTR_UPDATED_DT';

    protected $fillable = [
        'LOG_NAME',
        'LOG_DESC',
        'LOG_SUBJECT_TYPE',
        'LOG_SUBJECT_ID',
        'LOG_EVENT',
        'LOG_CAUSER_TYPE',
        'LOG_CAUSER_ID',
        'LOG_PROPERTIES',
        'LOG_BATCH_UUID',
        'LOG_OLD_VALUES',
        'LOG_NEW_VALUES',
        'REF_CONTR_CREATED_BY',
        'REF_CONTR_UPDATED_BY',
    ];

    protected function casts(): array
    {
        return [
            'LOG_PROPERTIES' => 'array',
            'LOG_OLD_VALUES' => 'array',
            'LOG_NEW_VALUES' => 'array',
        ];
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'LOG_CAUSER_ID');
    }

    /**
     * Get the created_at attribute.
     */
    public function getCreatedAtAttribute(): mixed
    {
        return $this->REF_CONTR_CREATED_DT;
    }

    /**
     * Get the updated_at attribute.
     */
    public function getUpdatedAtAttribute(): mixed
    {
        return $this->REF_CONTR_UPDATED_DT;
    }

    /**
     * Get the loggable entity.
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo(null, 'LOG_SUBJECT_TYPE', 'LOG_SUBJECT_ID');
    }

    /**
     * Get action label in English.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->LOG_EVENT) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default => $this->LOG_EVENT,
        };
    }

    /**
     * Get the action description.
     */
    public function getActionAttribute(): string
    {
        return $this->LOG_DESC ?? $this->action_label;
    }

    /**
     * Get detailed changes description.
     */
    public function getChangesDescriptionAttribute(): ?string
    {
        if ($this->LOG_EVENT !== 'updated' || ! $this->LOG_OLD_VALUES || ! $this->LOG_NEW_VALUES) {
            return null;
        }

        $changes = [];
        $fieldLabels = [
            'CONTR_NO' => 'Contract Number',
            'CONTR_DIV_ID' => 'Division',
            'CONTR_DEPT_ID' => 'Department',
            'CONTR_PIC_ID' => 'PIC',
            'CONTR_START_DT' => 'Start Date',
            'CONTR_END_DT' => 'End Date',
            'CONTR_IS_AUTO_RENEW' => 'Auto Renewal',
            'CONTR_DESC' => 'Description',
            'CONTR_STS_ID' => 'Status',
            'CONTR_DOC_DRAFT_PATH' => 'Document',
            'REF_DIV_DESC' => 'Description',
            'REF_DIV_NAME' => 'Division Name',
            'IS_ACTIVE' => 'Active Status',
        ];

        $statusLabels = [
            'draft' => 'Draft',
            'active' => 'Active',
            'expired' => 'Expired',
            'terminated' => 'Terminated',
        ];

        foreach ($fieldLabels as $field => $label) {
            $oldValue = $this->LOG_OLD_VALUES[$field] ?? null;
            $newValue = $this->LOG_NEW_VALUES[$field] ?? null;

            if ($oldValue != $newValue) {
                if ($field === 'CONTR_STS_ID') {
                    $oldValue = $statusLabels[$oldValue] ?? $oldValue;
                    $newValue = $statusLabels[$newValue] ?? $newValue;
                } elseif ($field === 'CONTR_IS_AUTO_RENEW') {
                    $oldValue = $oldValue ? 'Yes' : 'No';
                    $newValue = $newValue ? 'Yes' : 'No';
                } elseif (in_array($field, ['CONTR_START_DT', 'CONTR_END_DT'])) {
                    if ($oldValue) {
                        try {
                            $oldValue = \Carbon\Carbon::parse($oldValue)->format('d F Y');
                        } catch (\Exception $e) {
                        }
                    }
                    if ($newValue) {
                        try {
                            $newValue = \Carbon\Carbon::parse($newValue)->format('d F Y');
                        } catch (\Exception $e) {
                        }
                    }
                } elseif ($field === 'CONTR_DOC_DRAFT_PATH') {
                    $oldValue = $oldValue ? 'Exists' : 'None';
                    $newValue = $newValue ? 'Exists' : 'None';
                }

                if ($oldValue || $newValue) {
                    $changes[] = sprintf(
                        '%s changed from "%s" to "%s"',
                        $label,
                        $oldValue ?: '-',
                        $newValue ?: '-'
                    );
                }
            }
        }

        return ! empty($changes) ? implode(', ', $changes) : null;
    }
}

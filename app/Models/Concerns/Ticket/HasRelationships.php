<?php

namespace App\Models\Concerns\Ticket;

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\TicketAnswer;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasRelationships
{
    /**
     * Get the division for this ticket.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'DIV_ID');
    }

    /**
     * Get the department for this ticket.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'DEPT_ID');
    }

    /**
     * Get the status for this ticket.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'TCKT_STS_ID');
    }

    /**
     * Get the document type for this ticket.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'TCKT_DOC_TYPE_ID');
    }

    /**
     * Get the user who created this ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'TCKT_CREATED_BY');
    }

    /**
     * Get the legal user who reviewed this ticket.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'TCKT_REVIEWED_BY');
    }

    /**
     * Get the contract created from this ticket (if approved).
     */
    public function contract(): HasOne
    {
        // Contract has FK TCKT_ID
        return $this->hasOne(Contract::class, 'TCKT_ID');
    }

    /**
     * Get the activity logs for this ticket.
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject', 'LOG_SUBJECT_TYPE', 'LOG_SUBJECT_ID');
    }

    /**
     * Get the dynamic answers for this ticket.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(TicketAnswer::class, 'ANS_TICKET_ID');
    }

    /**
     * Get a single answer value by question code.
     */
    public function getAnswer(string $questionCode): ?string
    {
        return $this->answers
            ->first(fn ($answer) => $answer->question?->QUEST_CODE === $questionCode)
            ?->ANS_VALUE;
    }
}

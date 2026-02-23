<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAnswer extends Model
{
    protected $table = 'LGL_TICKET_ANSWER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'ANS_CREATED_DT';

    const UPDATED_AT = 'ANS_UPDATED_DT';

    protected $fillable = [
        'ANS_TICKET_ID',
        'ANS_QUESTION_ID',
        'ANS_VALUE',
    ];

    /**
     * Get the ticket this answer belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ANS_TICKET_ID');
    }

    /**
     * Get the question this answer responds to.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(FormQuestion::class, 'ANS_QUESTION_ID');
    }
}

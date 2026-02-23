<?php

namespace App\Models;

use App\Models\Concerns\Ticket\HasAttributes;
use App\Models\Concerns\Ticket\HasRelationships;
use App\Models\Concerns\Ticket\HasScopes;
use App\Models\Concerns\Ticket\InteractsWithState;
use App\Observers\TicketObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasAttributes, HasFactory, HasRelationships, HasScopes, InteractsWithState;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::observe(TicketObserver::class);

        static::updating(function ($ticket) {
            if (auth()->check()) {
                $ticket->TCKT_UPDATED_BY = auth()->user()->LGL_ROW_ID;
            }
        });
    }

    protected $table = 'LGL_TICKET_MASTER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'TCKT_CREATED_DT';

    const UPDATED_AT = 'TCKT_UPDATED_DT';

    protected $fillable = [
        'TCKT_NO',
        'DIV_ID',
        'DEPT_ID',
        'TCKT_DOC_TYPE_ID',
        'TCKT_STS_ID',
        'TCKT_REVIEWED_DT',
        'TCKT_REVIEWED_BY',
        'TCKT_AGING_START_DT',
        'TCKT_AGING_END_DT',
        'TCKT_AGING_DURATION',
        'TCKT_REJECT_REASON',
        'TCKT_CREATED_BY',
        'TCKT_UPDATED_BY',
    ];

    protected function casts(): array
    {
        return [
            'TCKT_REVIEWED_DT' => 'datetime',
            'TCKT_AGING_START_DT' => 'datetime',
            'TCKT_AGING_END_DT' => 'datetime',
        ];
    }
}

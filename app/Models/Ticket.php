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
    }

    protected $table = 'LGL_TICKET_MASTER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'TCKT_CREATED_DT';

    const UPDATED_AT = 'TCKT_UPDATED_DT';

    protected $fillable = [
        'TCKT_NO',
        'DIV_ID',
        'DEPT_ID',
        'TCKT_HAS_FIN_IMPACT',
        'TCKT_PAYMENT_TYPE',
        'TCKT_RECURRING_DESC',
        'TCKT_PROP_DOC_TITLE',
        'TCKT_DOC_PATH',
        'TCKT_DOC_TYPE_ID',
        'TCKT_COUNTERPART_NAME',
        'TCKT_AGREE_START_DT',
        'TCKT_AGREE_DURATION',
        'TCKT_IS_AUTO_RENEW',
        'TCKT_RENEW_PERIOD',
        'TCKT_RENEW_NOTIF_DAYS',
        'TCKT_AGREE_END_DT',
        'TCKT_TERMINATE_NOTIF_DT',
        'TCKT_GRANTOR',
        'TCKT_GRANTEE',
        'TCKT_GRANT_START_DT',
        'TCKT_GRANT_END_DT',
        'TCKT_TAT_LGL_COMPLNCE',
        'TCKT_DOC_REQUIRED_PATH',
        'TCKT_DOC_APPROVAL_PATH',
        'TCKT_STS_ID',
        'TCKT_REVIEWED_DT',
        'TCKT_REVIEWED_BY',
        'TCKT_AGING_START_DT',
        'TCKT_AGING_END_DT',
        'TCKT_AGING_DURATION',
        'TCKT_REJECT_REASON',
        'TCKT_POST_QUEST_1',
        'TCKT_POST_QUEST_2',
        'TCKT_POST_QUEST_3',
        'TCKT_POST_RMK',
        'TCKT_CREATED_BY',
    ];

    protected function casts(): array
    {
        return [
            'TCKT_HAS_FIN_IMPACT' => 'boolean',
            'TCKT_IS_AUTO_RENEW' => 'boolean',
            'TCKT_TAT_LGL_COMPLNCE' => 'boolean',
            'TCKT_DOC_REQUIRED_PATH' => 'array',
            'TCKT_AGREE_START_DT' => 'date',
            'TCKT_AGREE_END_DT' => 'date',
            'TCKT_GRANT_START_DT' => 'date',
            'TCKT_GRANT_END_DT' => 'date',
            'TCKT_TERMINATE_NOTIF_DT' => 'date',
            'TCKT_REVIEWED_DT' => 'datetime',
            'TCKT_AGING_START_DT' => 'datetime',
            'TCKT_AGING_END_DT' => 'datetime',
            'TCKT_POST_QUEST_1' => 'boolean',
            'TCKT_POST_QUEST_2' => 'boolean',
            'TCKT_POST_QUEST_3' => 'boolean',
        ];
    }
}

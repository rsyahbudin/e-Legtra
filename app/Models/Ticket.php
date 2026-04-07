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
        'TCKT_PROP_DOC_TITLE',
        'TCKT_COUNTERPART_NAME',
        'TCKT_AGREE_START_DT',
        'TCKT_AGREE_DURATION',
        'TCKT_IS_AUTO_RENEW',
        'TCKT_RENEW_PERIOD',
        'TCKT_RENEW_NOTIF_DAYS',
        'TCKT_AGREE_END_DT',
        'TCKT_DOC_PATH',
        'TCKT_POST_QUEST_1',
        'TCKT_POST_QUEST_2',
        'TCKT_POST_QUEST_3',
        'TCKT_POST_RMK',
        'TCKT_DOC_REQUIRED_PATH',
        'TCKT_DOC_APPROVAL_PATH',
    ];

    protected function casts(): array
    {
        return [
            'TCKT_REVIEWED_DT' => 'datetime',
            'TCKT_AGING_START_DT' => 'datetime',
            'TCKT_AGING_END_DT' => 'datetime',
            'TCKT_AGREE_START_DT' => 'datetime',
            'TCKT_AGREE_END_DT' => 'datetime',
        ];
    }

    /**
     * Sync standard dynamic question answers to physical legacy columns for Oracle reporting.
     */
    public function syncStandardAnswersToColumns(): void
    {
        $this->update([
            'TCKT_PROP_DOC_TITLE' => $this->getAnswer('proposed_document_title'),
            'TCKT_COUNTERPART_NAME' => $this->getAnswer('contract_party_name') ?? $this->getAnswer('counterpart_name') ?? $this->getAnswer('nda_counterpart_name') ?? $this->getAnswer('kuasa_penerima'),
            'TCKT_AGREE_START_DT' => $this->getAnswer('contract_start_date') ?? $this->getAnswer('agreement_start_date') ?? $this->getAnswer('nda_agreement_start_date') ?? $this->getAnswer('kuasa_start_date'),
            'TCKT_AGREE_END_DT' => $this->getAnswer('contract_end_date') ?? $this->getAnswer('agreement_end_date') ?? $this->getAnswer('nda_agreement_end_date') ?? $this->getAnswer('kuasa_end_date'),
            'TCKT_IS_AUTO_RENEW' => (bool) ($this->getAnswer('contract_is_auto_renew') ?? $this->getAnswer('is_auto_renewal') ?? $this->getAnswer('nda_is_auto_renewal') ?? false),
            'TCKT_AGREE_DURATION' => $this->getAnswer('agreement_duration') ?? $this->getAnswer('nda_agreement_duration') ?? $this->getAnswer('kuasa_duration'),
            'TCKT_RENEW_PERIOD' => $this->getAnswer('auto_renewal_period') ?? $this->getAnswer('nda_auto_renewal_period'),
            'TCKT_RENEW_NOTIF_DAYS' => $this->getAnswer('renewal_notification_days') ?? $this->getAnswer('nda_renewal_notification_days'),
            'TCKT_DOC_PATH' => $this->getAnswer('final_contract_file'),
            'TCKT_POST_QUEST_1' => $this->getAnswer('signed_by_both_parties'),
            'TCKT_POST_QUEST_2' => $this->getAnswer('saved_in_sharing_folder'),
            'TCKT_POST_QUEST_3' => $this->getAnswer('mandatory_attachments_complete'),
            'TCKT_POST_RMK' => $this->getAnswer('finalization_remarks'),
            'TCKT_DOC_REQUIRED_PATH' => $this->getAnswer('mandatory_documents'),
            'TCKT_DOC_APPROVAL_PATH' => $this->getAnswer('approval_document'),
        ]);
    }
}

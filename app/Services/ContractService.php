<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Division;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;

class ContractService
{
    /**
     * Generate unique contract number: CTR-{DIV_CODE}-{YYMM}{9999}
     * Resets sequence yearly per division.
     */
    public function generateContractNumber(int $divisionId): string
    {
        $division = Division::find($divisionId);

        if (! $division) {
            throw new \Exception("Division not found for ID: {$divisionId}");
        }

        $divCode = strtoupper(substr($division->code ?? 'UNK', 0, 3));

        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = "CTR-{$divCode}-{$year}{$month}";

        $lastContract = Contract::where('CONTR_NO', 'like', "{$prefix}%")
            ->orderBy('CONTR_NO', 'desc')
            ->first();

        if ($lastContract) {
            $lastNumber = (int) substr($lastContract->CONTR_NO, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix.$newNumber;
    }

    /**
     * Create a contract from a completed ticket.
     * Reads dynamic answers from LGL_TICKET_ANSWER via Ticket::getAnswer().
     */
    public function createFromTicket(Ticket $ticket): Contract
    {
        $ticket->load('answers.question');
        $docCode = $ticket->documentType?->code;

        $status = 'active';
        $endDate = null;
        $startDate = null;
        $isAutoRenew = false;
        $counterpart = null;
        $description = null;

        // Dynamically find standard contract fields from ticket answers.
        // If an admin creates a new DocumentType in the DB, as long as they use keys like 'contract_start_date' or 'agreement_start_date', it will work automatically without hardcoding logic.
        $startDate = $ticket->getAnswer('contract_start_date') ?? $ticket->getAnswer('agreement_start_date') ?? $ticket->getAnswer('nda_agreement_start_date') ?? $ticket->getAnswer('kuasa_start_date');
        
        $endDate = $ticket->getAnswer('contract_end_date') ?? $ticket->getAnswer('agreement_end_date') ?? $ticket->getAnswer('nda_agreement_end_date') ?? $ticket->getAnswer('kuasa_end_date');
        
        $isAutoRenew = (bool) ($ticket->getAnswer('contract_is_auto_renew') ?? $ticket->getAnswer('is_auto_renewal') ?? $ticket->getAnswer('nda_is_auto_renewal') ?? false);
        
        $counterpart = $ticket->getAnswer('contract_party_name') ?? $ticket->getAnswer('counterpart_name') ?? $ticket->getAnswer('nda_counterpart_name') ?? $ticket->getAnswer('kuasa_penerima');

        // Optional specific descriptions for legacy formats
        $grantor = $ticket->getAnswer('contract_grantor') ?? $ticket->getAnswer('kuasa_pemberi');
        if ($grantor) {
            $description = "Pemberi Kuasa: {$grantor}, Penerima: {$counterpart}";
        } else {
            $description = $counterpart ? "Pihak Lawan: {$counterpart}" : null;
        }

        $parsedEndDate = $endDate ? \Carbon\Carbon::parse($endDate) : null;

        if ($parsedEndDate && $parsedEndDate->isPast() && ! $isAutoRenew) {
            $status = 'expired';
        }

        $docTitle = $ticket->getAnswer('proposed_document_title');

        return $ticket->contract()->create([
            'CONTR_NO' => $this->generateContractNumber($ticket->DIV_ID),
            'CONTR_AGREE_NAME' => $docTitle,
            'CONTR_DOC_TYPE_ID' => $ticket->TCKT_DOC_TYPE_ID,
            'CONTR_DIV_ID' => $ticket->DIV_ID,
            'CONTR_DEPT_ID' => $ticket->DEPT_ID,
            'CONTR_PIC_ID' => $ticket->TCKT_CREATED_BY,
            'CONTR_START_DT' => $startDate ? \Carbon\Carbon::parse($startDate) : null,
            'CONTR_END_DT' => $parsedEndDate,
            'CONTR_IS_AUTO_RENEW' => $isAutoRenew,
            'CONTR_DESC' => $description,
            'CONTR_STS_ID' => ContractStatus::getIdByCode($status),
            'CONTR_CREATED_BY' => auth()->user()?->LGL_ROW_ID ?? $ticket->TCKT_REVIEWED_BY ?? $ticket->TCKT_CREATED_BY,
        ]);
    }

    /**
     * Terminate a contract before its end date.
     */
    public function terminate(Contract $contract, string $reason): void
    {
        $contract->update([
            'CONTR_STS_ID' => ContractStatus::getIdByCode('terminated'),
            'CONTR_TERMINATE_DT' => now(),
            'CONTR_TERMINATE_REASON' => $reason,
        ]);

        // Auto-close associated ticket
        if ($contract->ticket && $contract->ticket->status?->LOV_VALUE !== 'closed') {
            $contract->ticket->update(['TCKT_STS_ID' => TicketStatus::getIdByCode('closed')]);

            $contract->ticket->activityLogs()->create([
                'LOG_CAUSER_ID' => auth()->user()?->LGL_ROW_ID,
                'LOG_CAUSER_TYPE' => User::class,
                'LOG_EVENT' => 'status_change',
                'LOG_DESC' => 'Ticket automatically closed due to contract termination',
                'LOG_PROPERTIES' => [
                    'ticket_number' => $contract->ticket->TCKT_NO,
                    'status' => 'closed',
                ],
                'LOG_NAME' => 'ticket_activity',
            ]);
        }

        $contract->activityLogs()->create([
            'LOG_CAUSER_ID' => auth()->user()?->LGL_ROW_ID,
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'contract_terminated',
            'LOG_NAME' => "Contract terminated: {$reason}",
            'LOG_DESC' => "Contract {$contract->CONTR_NO} terminated at {$contract->CONTR_TERMINATE_DT->toDateTimeString()}",
        ]);
    }
}

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
     */
    public function createFromTicket(Ticket $ticket): Contract
    {
        $status = 'active';
        $endDate = $ticket->TCKT_AGREE_END_DT;

        if ($ticket->documentType?->code === 'surat_kuasa') {
            $endDate = $ticket->TCKT_GRANT_END_DT;
        }

        if ($endDate && $endDate->isPast() && ! $ticket->TCKT_IS_AUTO_RENEW) {
            $status = 'expired';
        }

        return $ticket->contract()->create([
            'CONTR_NO' => $this->generateContractNumber($ticket->DIV_ID),
            'CONTR_AGREE_NAME' => $ticket->TCKT_PROP_DOC_TITLE,
            'CONTR_PROP_DOC_TITLE' => $ticket->TCKT_PROP_DOC_TITLE,
            'CONTR_DOC_TYPE_ID' => $ticket->TCKT_DOC_TYPE_ID,
            'CONTR_HAS_FIN_IMPACT' => $ticket->TCKT_HAS_FIN_IMPACT,
            'CONTR_TAT_LGL_COMPLNCE' => $ticket->TCKT_TAT_LGL_COMPLNCE,
            'CONTR_DIV_ID' => $ticket->DIV_ID,
            'CONTR_DEPT_ID' => $ticket->DEPT_ID,
            'CONTR_PIC_ID' => $ticket->TCKT_CREATED_BY,
            'CONTR_START_DT' => $ticket->documentType?->code === 'surat_kuasa' ? $ticket->TCKT_GRANT_START_DT : $ticket->TCKT_AGREE_START_DT,
            'CONTR_END_DT' => $endDate,
            'CONTR_IS_AUTO_RENEW' => $ticket->TCKT_IS_AUTO_RENEW,
            'CONTR_DESC' => $ticket->TCKT_COUNTERPART_NAME
                ? "Pihak Lawan: {$ticket->TCKT_COUNTERPART_NAME}"
                : ($ticket->documentType?->code === 'surat_kuasa' ? "Pemberi Kuasa: {$ticket->TCKT_GRANTOR}, Penerima: {$ticket->TCKT_GRANTEE}" : null),
            'CONTR_STS_ID' => ContractStatus::getIdByCode($status),
            'CONTR_DOC_REQUIRED_PATH' => $ticket->TCKT_DOC_REQUIRED_PATH,
            'CONTR_DOC_APPROVAL_PATH' => $ticket->TCKT_DOC_APPROVAL_PATH,
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

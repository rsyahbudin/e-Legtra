<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;

class TicketService
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    /**
     * Generate unique ticket number: TIC-{DIV_CODE}-{YYMM}{9999}
     * Resets sequence yearly per division.
     */
    public function generateTicketNumber(int $divisionId): string
    {
        $division = Division::find($divisionId);

        if (! $division) {
            throw new \Exception("Division not found for ID: {$divisionId}");
        }

        $divCode = strtoupper(substr($division->code ?? 'UNK', 0, 3));

        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = "TIC-{$divCode}-{$year}{$month}";

        $lastTicket = Ticket::where('TCKT_NO', 'like', "{$prefix}%")
            ->orderBy('TCKT_NO', 'desc')
            ->first();

        if ($lastTicket) {
            $lastNumber = (int) substr($lastTicket->TCKT_NO, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix.$newNumber;
    }

    /**
     * Check if ticket can be reviewed (moved to on_process).
     */
    public function canBeReviewed(Ticket $ticket): bool
    {
        return $ticket->status?->LOV_VALUE === 'open';
    }

    /**
     * Move ticket to "on_process" status.
     */
    public function moveToOnProcess(Ticket $ticket, User $reviewer): void
    {
        $ticket->update([
            'TCKT_STS_ID' => TicketStatus::getIdByCode('on_process'),
            'TCKT_REVIEWED_DT' => now(),
            'TCKT_REVIEWED_BY' => $reviewer->LGL_ROW_ID,
            'TCKT_AGING_START_DT' => now(),
        ]);

        $this->logTicketActivity($ticket, 'Ticket moved to On Process status', $reviewer);
    }

    /**
     * Move ticket to "done" status and calculate aging.
     * Finalization answers are saved by the component before calling this.
     */
    public function moveToDone(Ticket $ticket): void
    {
        $agingEnd = now();
        $agingDuration = null;

        if ($ticket->TCKT_AGING_START_DT) {
            $agingDuration = $ticket->TCKT_AGING_START_DT->diffInMinutes($agingEnd);
        }

        $ticket->update([
            'TCKT_STS_ID' => TicketStatus::getIdByCode('done'),
            'TCKT_AGING_END_DT' => $agingEnd,
            'TCKT_AGING_DURATION' => $agingDuration,
        ]);

        $this->logTicketActivity($ticket, 'Ticket completed (Done)');
    }

    /**
     * Reject ticket with reason.
     */
    public function reject(Ticket $ticket, string $reason, User $reviewer): void
    {
        $agingEnd = now();
        $agingDuration = null;

        if ($ticket->TCKT_AGING_START_DT) {
            $agingDuration = $ticket->TCKT_AGING_START_DT->diffInMinutes($agingEnd);
        }

        $ticket->update([
            'TCKT_STS_ID' => TicketStatus::getIdByCode('rejected'),
            'TCKT_REVIEWED_DT' => now(),
            'TCKT_REVIEWED_BY' => $reviewer->LGL_ROW_ID,
            'TCKT_REJECT_REASON' => $reason,
            'TCKT_AGING_END_DT' => $agingEnd,
            'TCKT_AGING_DURATION' => $agingDuration,
        ]);

        $this->logTicketActivity($ticket, "Ticket rejected: {$reason}", $reviewer);
    }

    /**
     * Move ticket directly to "closed" status for non-contractable documents.
     */
    public function moveToClosedDirectly(Ticket $ticket): void
    {
        $agingEnd = now();
        $agingDuration = null;

        if ($ticket->TCKT_AGING_START_DT) {
            $agingDuration = $ticket->TCKT_AGING_START_DT->diffInMinutes($agingEnd);
        }

        $ticket->update([
            'TCKT_STS_ID' => TicketStatus::getIdByCode('closed'),
            'TCKT_AGING_END_DT' => $agingEnd,
            'TCKT_AGING_DURATION' => $agingDuration,
        ]);

        $this->logTicketActivity($ticket, 'Ticket closed (does not require contract)');
    }

    /**
     * Log activity for a ticket via the polymorphic relationship.
     */
    private function logTicketActivity(Ticket $ticket, string $message, ?User $user = null): void
    {
        $ticket->activityLogs()->create([
            'LOG_CAUSER_ID' => $user?->LGL_ROW_ID ?? auth()->id(),
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'status_change',
            'LOG_DESC' => $message,
            'LOG_PROPERTIES' => [
                'ticket_number' => $ticket->TCKT_NO,
                'status' => $ticket->status?->LOV_VALUE,
            ],
            'LOG_NAME' => 'ticket_activity',
        ]);
    }
}

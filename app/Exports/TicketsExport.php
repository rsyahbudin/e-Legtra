<?php

namespace App\Exports;

use App\Models\Ticket;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketsExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
{
    protected ?string $statusFilter;

    protected ?string $typeFilter;

    protected ?int $divisionId;

    protected ?string $startDate;

    protected ?string $endDate;

    public function __construct(
        ?string $statusFilter = null,
        ?string $typeFilter = null,
        ?int $divisionId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $this->statusFilter = $statusFilter;
        $this->typeFilter = $typeFilter;
        $this->divisionId = $divisionId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        $query = Ticket::with(['division', 'department', 'creator', 'contract', 'status'])
            ->when($this->statusFilter, fn ($q) => $q->whereHas('status', fn ($sq) => $sq->where('LOV_VALUE', $this->statusFilter)))
            ->when($this->typeFilter, fn ($q) => $q->whereHas('documentType', fn ($sq) => $sq->where('code', $this->typeFilter)))
            ->when($this->divisionId, fn ($q) => $q->where('DIV_ID', $this->divisionId))
            ->when($this->startDate, fn ($q) => $q->whereDate('TCKT_CREATED_DT', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('TCKT_CREATED_DT', '<=', $this->endDate))
            ->orderBy('TCKT_CREATED_DT', 'desc');

        return $query->get()->map(function ($ticket) {
            // Calculate aging based on proper workflow timestamps
            $agingDisplay = '-';
            $totalMinutes = 0;

            // Aging calculation logic based on status and TCKT_AGING_START_DT
            if ($ticket->TCKT_AGING_DURATION && $ticket->TCKT_AGING_DURATION > 0) {
                // For completed tickets with stored TCKT_AGING_DURATION (already in minutes)
                $totalMinutes = $ticket->TCKT_AGING_DURATION;
            } elseif (in_array($ticket->status?->LOV_VALUE, ['done', 'closed', 'rejected']) && $ticket->TCKT_AGING_START_DT) {
                // For completed tickets: use TCKT_AGING_START_DT to TCKT_AGING_END_DT (or TCKT_UPDATED_DT as fallback)
                $endTime = $ticket->TCKT_AGING_END_DT ?? $ticket->TCKT_UPDATED_DT;
                $totalMinutes = $ticket->TCKT_AGING_START_DT->diffInMinutes($endTime);
            } elseif ($ticket->status?->LOV_VALUE === 'on_process' && $ticket->TCKT_AGING_START_DT) {
                // For in-progress tickets: calculate from TCKT_AGING_START_DT to now
                $totalMinutes = $ticket->TCKT_AGING_START_DT->diffInMinutes(now());
            }
            // If TCKT_AGING_START_DT is not set, aging stays as '-' (ticket hasn't been processed yet)

            // Always show in hours for export
            if ($totalMinutes > 0) {
                $hours = round($totalMinutes / 60, 1); // Convert minutes to hours with 1 decimal
                $agingDisplay = $hours.' hours';
            }

            return [
                'Ticket Number' => $ticket->TCKT_NO,
                'Document Title' => $ticket->TCKT_PROP_DOC_TITLE,
                'Document Type' => $ticket->document_type_label,
                'Division' => $ticket->division?->REF_DIV_NAME ?? '-',
                'Department' => $ticket->department?->REF_DEPT_NAME ?? '-',
                'Created By' => $ticket->creator?->USER_FULLNAME ?? $ticket->creator?->name ?? '-',
                'Created Date' => $ticket->TCKT_CREATED_DT->format('d/m/Y H:i'),
                'Last Updated' => $ticket->TCKT_UPDATED_DT->format('d/m/Y H:i'),
                'Status' => $ticket->status_label,
                'Contract Status' => $ticket->contract?->status?->LOV_DISPLAY_NAME ?? '-',

                // Aging information
                'Process Started' => $ticket->TCKT_AGING_START_DT?->format('d/m/Y H:i') ?? '-',
                'Process Ended' => $ticket->TCKT_AGING_END_DT?->format('d/m/Y H:i') ?? '-',
                'Aging' => $agingDisplay,

                // Agreement/Perjanjian fields
                'Counterpart' => $ticket->TCKT_COUNTERPART_NAME ?? '-',
                'Agreement Start Date' => $ticket->TCKT_AGREE_START_DT?->format('d/m/Y') ?? '-',
                'Agreement Duration (Months)' => $ticket->TCKT_AGREE_DURATION ?? '-',
                'Auto Renewal' => $ticket->TCKT_IS_AUTO_RENEW ? 'Yes' : 'No',
                'Renewal Period (Months)' => $ticket->TCKT_RENEW_PERIOD ?? '-',
                'Renewal Notification (Days)' => $ticket->TCKT_RENEW_NOTIF_DAYS ?? '-',
                'Agreement End Date' => $ticket->TCKT_AGREE_END_DT?->format('d/m/Y') ?? '-',
                'Termination Notification (Days)' => $ticket->TCKT_TERMINATE_NOTIF_DT ? ($ticket->TCKT_TERMINATE_NOTIF_DT instanceof \Carbon\Carbon ? $ticket->TCKT_TERMINATE_NOTIF_DT->diffInDays($ticket->TCKT_AGREE_END_DT) : $ticket->TCKT_TERMINATE_NOTIF_DT) : '-',

                // Surat Kuasa fields
                'Grantor' => $ticket->TCKT_GRANTOR ?? '-',
                'Grantee' => $ticket->TCKT_GRANTEE ?? '-',
                'Power of Attorney Start Date' => $ticket->TCKT_GRANT_START_DT?->format('d/m/Y') ?? '-',
                'Power of Attorney End Date' => $ticket->TCKT_GRANT_END_DT?->format('d/m/Y') ?? '-',

                // Common fields
                'Financial Impact' => $ticket->TCKT_HAS_FIN_IMPACT ? 'Yes' : 'No',
                'Payment Type' => match ($ticket->TCKT_PAYMENT_TYPE) {
                    'pay' => 'Pay',
                    'receive_payment' => 'Receive Payment',
                    default => '-'
                },
                'Recurring' => $ticket->TCKT_RECURRING_DESC ?? '-',
                'TAT Legal Compliance' => $ticket->TCKT_TAT_LGL_COMPLNCE ? 'Yes' : 'No',

                // Pre-Done Questions (Checklist)
                'All requirements completed?' => $ticket->TCKT_POST_QUEST_1 ? 'Yes' : 'No',
                'Stakeholder agreed?' => $ticket->TCKT_POST_QUEST_2 ? 'Yes' : 'No',
                'Project started?' => $ticket->TCKT_POST_QUEST_3 ? 'Yes' : 'No',
                'PreDone Notes' => $ticket->TCKT_POST_RMK ?? '-',

                'Contract Number' => $ticket->contract?->CONTR_NO ?? '-',
                'Rejection Reason' => $ticket->TCKT_REJECT_REASON ?? '-',
                'Termination Reason' => $ticket->contract?->CONTR_TERMINATE_REASON ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Ticket Number',
            'Document Title',
            'Document Type',
            'Division',
            'Department',
            'Created By',
            'Created Date',
            'Last Updated',
            'Status',
            'Contract Status',
            'Process Started',
            'Process Ended',
            'Aging',
            'Counterpart',
            'Agreement Start Date',
            'Agreement Duration (Months)',
            'Auto Renewal',
            'Renewal Period (Months)',
            'Renewal Notification (Days)',
            'Agreement End Date',
            'Termination Notification (Days)',
            'Grantor',
            'Grantee',
            'Power of Attorney Start Date',
            'Power of Attorney End Date',
            'Financial Impact',
            'Payment Type',
            'Recurring',
            'TAT Legal Compliance',
            'All requirements completed?',
            'Stakeholder agreed?',
            'Project started?',
            'PreDone Notes',
            'Contract Number',
            'Rejection Reason',
            'Termination Reason',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style header row
        $sheet->getStyle('A1:AJ1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'], // Blue background
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Set header row height
        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // Ticket Number
            'B' => 40,  // Document Title
            'C' => 20,  // Document Type
            'D' => 20,  // Division
            'E' => 20,  // Department
            'F' => 20,  // Created By
            'G' => 18,  // Created Date
            'H' => 18,  // Updated Date
            'I' => 15,  // Status
            'J' => 18,  // Contract Status
            'K' => 18,  // Processing Started
            'L' => 18,  // Processing Ended
            'M' => 15,  // Aging
            'N' => 30,  // Counterpart
            'O' => 20,  // Agreement Start
            'P' => 20,  // Agreement Duration
            'Q' => 15,  // Auto Renewal
            'R' => 20,  // Renewal Period
            'S' => 22,  // Renewal Notification
            'T' => 20,  // Agreement End
            'U' => 25,  // Termination Notification
            'V' => 25,  // Kuasa Pemberi
            'W' => 25,  // Kuasa Penerima
            'X' => 20,  // Kuasa Start
            'Y' => 20,  // Kuasa End
            'Z' => 18,  // Financial Impact
            'AA' => 20, // Payment Type
            'AB' => 30, // Recurring
            'AC' => 18, // TAT Legal
            'AD' => 30, // PreDone Q1
            'AE' => 30, // PreDone Q2
            'AF' => 30, // PreDone Q3
            'AG' => 40, // PreDone Remarks
            'AH' => 20, // Contract Number
            'AI' => 30, // Rejection Reason
            'AJ' => 30, // Termination Reason
        ];
    }

    public function toCsv(): string
    {
        $data = $this->collection();
        $headings = $this->headings();

        $csv = implode(',', $headings)."\n";

        foreach ($data as $row) {
            $values = array_map(function ($value) {
                // Escape quotes and wrap in quotes if contains comma
                $value = str_replace('"', '""', $value ?? '');
                if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                    return '"'.$value.'"';
                }

                return $value;
            }, $row);
            $csv .= implode(',', $values)."\n";
        }

        return $csv;
    }
}

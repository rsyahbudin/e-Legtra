<?php

namespace App\Exports;

use App\Models\FormQuestion;
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

    /**
     * Get all dynamic question codes in display order for export columns.
     */
    private function getDynamicQuestionCodes(): Collection
    {
        return FormQuestion::active()
            ->where('QUEST_CODE', '!=', 'proposed_document_title')
            ->ordered()
            ->get(['QUEST_CODE', 'QUEST_LABEL', 'QUEST_TYPE']);
    }

    public function collection(): Collection
    {
        $dynamicQuestions = $this->getDynamicQuestionCodes();

        $query = Ticket::with(['division', 'department', 'creator', 'contract', 'status', 'answers.question'])
            ->when($this->statusFilter, fn ($q) => $q->whereHas('status', fn ($sq) => $sq->where('LOV_VALUE', $this->statusFilter)))
            ->when($this->typeFilter, fn ($q) => $q->whereHas('documentType', fn ($sq) => $sq->where('code', $this->typeFilter)))
            ->when($this->divisionId, fn ($q) => $q->where('DIV_ID', $this->divisionId))
            ->when($this->startDate, fn ($q) => $q->whereDate('TCKT_CREATED_DT', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('TCKT_CREATED_DT', '<=', $this->endDate))
            ->orderBy('TCKT_CREATED_DT', 'desc');

        return $query->get()->map(function ($ticket) use ($dynamicQuestions) {
            // Calculate aging
            $agingDisplay = '-';
            $totalMinutes = 0;

            if ($ticket->TCKT_AGING_DURATION && $ticket->TCKT_AGING_DURATION > 0) {
                $totalMinutes = $ticket->TCKT_AGING_DURATION;
            } elseif (in_array($ticket->status?->LOV_VALUE, ['done', 'closed', 'rejected']) && $ticket->TCKT_AGING_START_DT) {
                $endTime = $ticket->TCKT_AGING_END_DT ?? $ticket->TCKT_UPDATED_DT;
                $totalMinutes = $ticket->TCKT_AGING_START_DT->diffInMinutes($endTime);
            } elseif ($ticket->status?->LOV_VALUE === 'on_process' && $ticket->TCKT_AGING_START_DT) {
                $totalMinutes = $ticket->TCKT_AGING_START_DT->diffInMinutes(now());
            }

            if ($totalMinutes > 0) {
                $hours = round($totalMinutes / 60, 1);
                $agingDisplay = $hours.' hours';
            }

            // Build row data as an indexed array to match headings and avoid key collisions
            $row = [
                $ticket->TCKT_NO,
                $ticket->getAnswer('proposed_document_title') ?? '-',
                $ticket->document_type_label,
                $ticket->division?->REF_DIV_NAME ?? '-',
                $ticket->department?->REF_DEPT_NAME ?? '-',
                $ticket->creator?->USER_FULLNAME ?? $ticket->creator?->name ?? '-',
                $ticket->TCKT_CREATED_DT->format('d/m/Y H:i'),
                $ticket->TCKT_UPDATED_DT?->format('d/m/Y H:i') ?? '-',
                $ticket->status_label,
                $ticket->contract?->status?->LOV_DISPLAY_NAME ?? '-',
                $ticket->TCKT_AGING_START_DT?->format('d/m/Y H:i') ?? '-',
                $ticket->TCKT_AGING_END_DT?->format('d/m/Y H:i') ?? '-',
                $agingDisplay,
            ];

            // Add dynamic question answers
            $answersMap = $ticket->answers->keyBy(fn ($a) => $a->question?->QUEST_CODE);

            foreach ($dynamicQuestions as $question) {
                $answer = $answersMap->get($question->QUEST_CODE);
                $value = $answer?->ANS_VALUE;

                if ($question->QUEST_TYPE === 'boolean') {
                    $displayValue = $value !== null ? ($value ? 'Yes' : 'No') : '-';
                } elseif ($question->QUEST_TYPE === 'date' && $value) {
                    try {
                        $displayValue = \Carbon\Carbon::parse($value)->format('d/m/Y');
                    } catch (\Exception $e) {
                        $displayValue = $value;
                    }
                } else {
                    $displayValue = $value ?? '-';
                }

                $row[] = $displayValue;
            }

            // Add contract info at end
            $row[] = $ticket->contract?->CONTR_NO ?? '-';
            $row[] = $ticket->TCKT_REJECT_REASON ?? '-';
            $row[] = $ticket->contract?->CONTR_TERMINATE_REASON ?? '-';

            return $row;
        });
    }

    public function headings(): array
    {
        $dynamicQuestions = $this->getDynamicQuestionCodes();

        $base = [
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
        ];

        foreach ($dynamicQuestions as $question) {
            $base[] = $question->QUEST_LABEL;
        }

        $base[] = 'Contract Number';
        $base[] = 'Rejection Reason';
        $base[] = 'Termination Reason';

        return $base;
    }

    public function styles(Worksheet $sheet)
    {
        $totalColumns = count($this->headings());
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColumns);

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // Ticket Number
            'B' => 45,  // Document Title
            'C' => 25,  // Document Type
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
            'N' => 18,  // Financial Impact
            'O' => 20,  // Payment Type
            'P' => 30,  // Recurring
            'Q' => 18,  // TAT Legal
        ];
    }

    public function toCsv(): string
    {
        $data = $this->collection();
        $headings = $this->headings();

        $csv = implode(',', $headings)."\n";

        foreach ($data as $row) {
            $values = array_map(function ($value) {
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

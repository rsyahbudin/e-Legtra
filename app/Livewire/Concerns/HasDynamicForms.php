<?php

namespace App\Livewire\Concerns;

use App\Models\DocumentType;
use App\Models\FormQuestion;
use App\Models\FormSection;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Services\LegalDocumentService;

trait HasDynamicForms
{
    /**
     * Get questions for a given section, respecting doc-type filtering.
     */
    public function getQuestionsForSection(?FormSection $section)
    {
        if (! $section) {
            return collect();
        }

        $query = FormQuestion::active()
            ->forSection($section->SECT_CODE)
            ->ordered();

        // 'form' and 'finalization' sections are doc-type-specific
        if (in_array($section->SECT_CODE, ['form', 'finalization'])) {
            // Check if ticket property exists and has a document type
            $ticketDocType = null;
            if (property_exists($this, 'ticket') && $this->ticket && $this->ticket->LGL_ROW_ID) {
                $ticketDocType = $this->ticket->documentType?->CODE;
            }

            $docType = $this->document_type ?: $ticketDocType;

            if (! $docType) {
                return collect();
            }

            $docTypeId = DocumentType::getIdByCode($docType);
            if ($docTypeId) {
                $query->forDocType($docTypeId);
            } else {
                return collect();
            }
        } else {
            // basic and supporting are generally doc-type neutral
            $query->forDocType(null);
        }

        return $query->get();
    }

    /**
     * Check if a dependent question should be visible.
     */
    public function isDependencyMet(FormQuestion $question, string $answersSource = 'dynamicAnswers'): bool
    {
        if (! $question->QUEST_DEPENDS_ON) {
            return true;
        }

        $answers = $this->{$answersSource} ?? [];
        $parentValue = $answers[$question->QUEST_DEPENDS_ON] ?? null;

        return (string) $parentValue === (string) $question->QUEST_DEPENDS_VALUE;
    }

    /**
     * Build dynamic validation rules for form questions.
     */
    protected function buildDynamicValidationRules($questions, string $prefix = 'dynamicAnswers', string $filesPrefix = 'dynamicFiles', array $existingAnswers = []): array
    {
        $rules = [];

        foreach ($questions as $question) {
            if ($question->QUEST_TYPE === 'file') {
                $hasExisting = ! empty($existingAnswers[$question->QUEST_CODE]);
                $rules = array_merge($rules, $this->getFileValidationRules($question, $filesPrefix, $hasExisting));

                continue;
            }

            if (! $this->isDependencyMet($question, $prefix)) {
                continue;
            }

            $fieldRules = [$question->QUEST_IS_REQUIRED ? 'required' : 'nullable'];

            match ($question->QUEST_TYPE) {
                'text', 'textarea' => $fieldRules[] = 'string',
                'number' => $fieldRules[] = 'numeric',
                'date' => $fieldRules[] = 'date',
                'boolean' => $fieldRules[] = 'in:0,1',
                'select' => $fieldRules[] = 'string',
                default => null,
            };

            $rules["{$prefix}.{$question->QUEST_CODE}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Get validation rules for a specific file question.
     */
    protected function getFileValidationRules(FormQuestion $question, string $prefix = 'dynamicFiles', bool $hasExisting = false): array
    {
        $rules = [];
        $code = $question->QUEST_CODE;

        // If an answer already exists (edit mode), the upload is optional (nullable)
        $isRequired = $question->QUEST_IS_REQUIRED && ! $hasExisting;

        $fileRules = ['file'];
        if ($question->QUEST_ACCEPT) {
            $mimes = collect(explode(',', $question->QUEST_ACCEPT))
                ->map(fn ($ext) => ltrim(trim($ext), '.'))
                ->filter()
                ->implode(',');
            if ($mimes) {
                $fileRules[] = "mimes:{$mimes}";
            }
        }
        $fileRules[] = 'max:'.($question->QUEST_MAX_SIZE_KB ?? 10240);

        if ($question->QUEST_IS_MULTIPLE) {
            // If required and no existing files, the array itself must be required and have at least 1 file
            $rules["{$prefix}.{$code}"] = [$isRequired ? 'required' : 'nullable', 'array'];
            if ($isRequired) {
                $rules["{$prefix}.{$code}"][] = 'min:1';
            }
            // Individual file rules
            $rules["{$prefix}.{$code}.*"] = array_merge(['required'], $fileRules);
        } else {
            // Single file
            $rules["{$prefix}.{$code}"] = array_merge([$isRequired ? 'required' : 'nullable'], $fileRules);
        }

        return $rules;
    }

    /**
     * Save non-file answers to LGL_TICKET_ANSWER.
     */
    protected function saveTextAnswers(Ticket $ticket, $questions, array $answers): void
    {
        foreach ($questions as $question) {
            if ($question->QUEST_TYPE === 'file') {
                continue;
            }

            $value = $answers[$question->QUEST_CODE] ?? null;

            if ($value === null || $value === '') {
                TicketAnswer::where('ANS_TICKET_ID', $ticket->LGL_ROW_ID)
                    ->where('ANS_QUESTION_ID', $question->LGL_ROW_ID)
                    ->delete();

                continue;
            }

            TicketAnswer::updateOrCreate(
                [
                    'ANS_TICKET_ID' => $ticket->LGL_ROW_ID,
                    'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
                ],
                ['ANS_VALUE' => (string) $value]
            );
        }
    }

    /**
     * Save file answers using LegalDocumentService.
     */
    protected function saveFileAnswers(Ticket $ticket, $fileQuestions, array $files): void
    {
        $documentService = app(LegalDocumentService::class);

        foreach ($fileQuestions as $question) {
            $fileData = $files[$question->QUEST_CODE] ?? null;

            if (! $fileData) {
                continue;
            }

            $category = $question->QUEST_SECTION === 'finalization' ? 'legal' : 'request';
            $prefix = $question->QUEST_FILE_NAME ?: $question->QUEST_CODE;

            if ($question->QUEST_IS_MULTIPLE && is_array($fileData)) {
                $paths = [];
                // Load existing if updating? Or always append?
                // For edit, we might want to append or replace.
                // Standard behavior in existing code seems to be append/replace handled by service?
                // Actually, existing code in create always creates new.
                // In edit, let's keep it simple: if new files are uploaded, they are stored.

                foreach ($fileData as $index => $file) {
                    $sequentialPrefix = $prefix.'_'.($index + 1);
                    $paths[] = $documentService->uploadDocument($file, $ticket->TCKT_NO, $category, $sequentialPrefix);
                }

                $existingAnswer = TicketAnswer::where('ANS_TICKET_ID', $ticket->LGL_ROW_ID)
                    ->where('ANS_QUESTION_ID', $question->LGL_ROW_ID)
                    ->first();
                $existingPaths = $existingAnswer ? json_decode($existingAnswer->ANS_VALUE, true) ?? [] : [];
                $allPaths = array_merge($existingPaths, $paths);

                TicketAnswer::updateOrCreate(
                    [
                        'ANS_TICKET_ID' => $ticket->LGL_ROW_ID,
                        'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
                    ],
                    ['ANS_VALUE' => json_encode($allPaths)]
                );
            } else {
                $path = $documentService->uploadDocument($fileData, $ticket->TCKT_NO, $category, $prefix);

                TicketAnswer::updateOrCreate(
                    [
                        'ANS_TICKET_ID' => $ticket->LGL_ROW_ID,
                        'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
                    ],
                    ['ANS_VALUE' => $path]
                );
            }
        }
    }

    /**
     * Delete an existing file from storage and update the database answer.
     */
    protected function deleteExistingFile(Ticket $ticket, FormQuestion $question, string $path): void
    {
        $documentService = app(LegalDocumentService::class);

        // 1. Delete matching file from storage (if service supports it)
        try {
            if (method_exists($documentService, 'deleteDocument')) {
                $documentService->deleteDocument($path);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not delete file from storage during removal', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Update TicketAnswer
        $answer = TicketAnswer::where('ANS_TICKET_ID', $ticket->LGL_ROW_ID)
            ->where('ANS_QUESTION_ID', $question->LGL_ROW_ID)
            ->first();

        if (! $answer) {
            return;
        }

        if ($question->QUEST_IS_MULTIPLE) {
            $paths = json_decode($answer->ANS_VALUE, true) ?? [];
            $newPaths = array_values(array_filter($paths, fn ($p) => $p !== $path));

            if (empty($newPaths)) {
                $answer->delete();
            } else {
                $answer->update(['ANS_VALUE' => json_encode($newPaths)]);
            }
        } else {
            $answer->delete();
        }
    }
}

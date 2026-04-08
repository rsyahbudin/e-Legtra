<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\FormQuestion;
use App\Models\FormSection;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Models\User;
use App\Livewire\Concerns\HasDynamicForms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads, HasDynamicForms;

    public Ticket $ticket;

    // Structural fields (read-only display)
    public $division_id;

    public $department_id;

    public string $document_type = '';

    // Dynamic answers (keyed by question code) — covers basic, form, supporting
    public array $dynamicAnswers = [];

    // Finalization answers (keyed by question code)
    public array $finalizationAnswers = [];

    // Dynamic file uploads (keyed by question code)
    public array $dynamicFiles = [];

    public function mount(int $contract): void
    {
        $this->ticket = Ticket::with(['answers.question'])->findOrFail($contract);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->hasAnyRole(['super-admin', 'legal']) && $this->ticket->TCKT_CREATED_BY !== $user->LGL_ROW_ID) {
            abort(403, 'You do not have permission to edit this ticket.');
        }

        // Populate structural fields (read-only)
        $this->division_id = $this->ticket->DIV_ID;
        $this->department_id = $this->ticket->DEPT_ID;
        $this->document_type = $this->ticket->documentType?->CODE ?? '';

        // Populate dynamic answers from existing ticket answers
        foreach ($this->ticket->answers as $answer) {
            $code = $answer->question?->QUEST_CODE;
            if (! $code) {
                continue;
            }

            $section = $answer->question->QUEST_SECTION;
            if ($section === 'finalization') {
                $this->finalizationAnswers[$code] = $answer->ANS_VALUE;
            } else {
                $this->dynamicAnswers[$code] = $answer->ANS_VALUE;
            }
        }
    }

    #[Computed]
    public function getSectionsProperty()
    {
        return FormSection::active()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function getDocumentTypesProperty()
    {
        return DocumentType::active()->get();
    }

    /**
     * Hook when document_type changes.
     */
    public function updatedDocumentType(): void
    {
        $allQuestions = collect();
        foreach ($this->sections as $section) {
            $allQuestions = $allQuestions->merge($this->getQuestionsForSection($section));
        }

        foreach ($allQuestions as $question) {
            if ($question->QUEST_TYPE !== 'file' && ! isset($this->dynamicAnswers[$question->QUEST_CODE]) && ! isset($this->finalizationAnswers[$question->QUEST_CODE])) {
                if ($question->QUEST_SECTION === 'finalization') {
                    $this->finalizationAnswers[$question->QUEST_CODE] = '';
                } else {
                    $this->dynamicAnswers[$question->QUEST_CODE] = '';
                }
            }
        }
    }

    /**
     * Get the ticket's division name for display.
     */
    #[Computed]
    public function getTicketDivisionProperty(): string
    {
        return Division::find($this->division_id)?->REF_DIV_NAME ?? '-';
    }

    /**
     * Get the ticket's department name for display.
     */
    #[Computed]
    public function getTicketDepartmentProperty(): string
    {
        return Department::find($this->department_id)?->REF_DEPT_NAME ?? '-';
    }

    /**
     * Remove an existing file.
     */
    public function removeFile(string $questionCode, string $path): void
    {
        $question = FormQuestion::where('QUEST_CODE', $questionCode)->first();

        if ($question) {
            $this->deleteExistingFile($this->ticket, $question, $path);

            // Refresh answers
            $answer = TicketAnswer::where('ANS_TICKET_ID', $this->ticket->LGL_ROW_ID)
                ->where('ANS_QUESTION_ID', $question->LGL_ROW_ID)
                ->first();

            if ($question->QUEST_SECTION === 'finalization') {
                $this->finalizationAnswers[$questionCode] = $answer ? $answer->ANS_VALUE : null;
            } else {
                $this->dynamicAnswers[$questionCode] = $answer ? $answer->ANS_VALUE : null;
            }

            $this->dispatch('file-removed');
        }
    }

    public function save(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only legal team can edit tickets.');

            return;
        }

        // 1. Collect all relevant questions for validation and labels
        $allQuestions = collect();
        $validationAttributes = [];
        foreach ($this->sections as $section) {
            if ($section->SECT_CODE === 'finalization' && $this->ticket->status?->LOV_VALUE !== 'done') {
                continue;
            }
            $sectionQuestions = $this->getQuestionsForSection($section);
            $allQuestions = $allQuestions->merge($sectionQuestions);

            foreach ($sectionQuestions as $question) {
                $validationAttributes["dynamicAnswers.{$question->QUEST_CODE}"] = $question->QUEST_LABEL;
                $validationAttributes["finalizationAnswers.{$question->QUEST_CODE}"] = $question->QUEST_LABEL;
                $validationAttributes["dynamicFiles.{$question->QUEST_CODE}"] = $question->QUEST_LABEL;
                $validationAttributes["dynamicFiles.{$question->QUEST_CODE}.*"] = $question->QUEST_LABEL;
            }
        }

        // 2. Build validation rules — only validate editable questions
        $editableQuestions = $allQuestions->filter(fn ($q) => (bool) $q->QUEST_IS_EDITABLE);

        $rules = [
            'document_type' => ['required', Rule::in(DocumentType::active()->get()->pluck('CODE')->toArray())],
        ];

        // Rules for standard editable questions (basic, form, supporting)
        $standardEditable = $editableQuestions->whereNotIn('QUEST_SECTION', ['finalization']);
        $rules = array_merge($rules, $this->buildDynamicValidationRules($standardEditable, 'dynamicAnswers', 'dynamicFiles', $this->dynamicAnswers));

        // Rules for finalization questions (if ticket is done)
        if ($this->ticket->status?->LOV_VALUE === 'done') {
            $finalizationEditable = $editableQuestions->where('QUEST_SECTION', 'finalization');
            $rules = array_merge($rules, $this->buildDynamicValidationRules($finalizationEditable, 'finalizationAnswers', 'dynamicFiles', $this->finalizationAnswers));
        }

        $this->validate($rules, [], $validationAttributes);

        // 3. Update ticket structural fields
        $this->ticket->update([
            'TCKT_DOC_TYPE_ID' => DocumentType::getIdByCode($this->document_type),
        ]);

        // 4. Save answers — only editable questions
        $this->saveTextAnswers($this->ticket, $standardEditable->filter(fn ($q) => $q->QUEST_TYPE !== 'file'), $this->dynamicAnswers);

        if ($this->ticket->status?->LOV_VALUE === 'done') {
            $finalizationText = $editableQuestions->where('QUEST_SECTION', 'finalization')->filter(fn ($q) => $q->QUEST_TYPE !== 'file');
            $this->saveTextAnswers($this->ticket, $finalizationText, $this->finalizationAnswers);
        }

        // 5. Save file answers dynamically
        $fileQuestions = $editableQuestions->where('QUEST_TYPE', 'file');
        $this->saveFileAnswers($this->ticket, $fileQuestions, $this->dynamicFiles);

        // 6. Log activity
        $this->ticket->activityLogs()->create([
            'LOG_CAUSER_ID' => $user->LGL_ROW_ID,
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'updated',
            'LOG_DESC' => 'Updated ticket details',
            'LOG_PROPERTIES' => [
                'ticket_number' => $this->ticket->TCKT_NO,
                'status' => $this->ticket->status?->LOV_VALUE,
                'updated_by' => $user->name,
            ],
            'LOG_NAME' => 'ticket_activity',
        ]);

        // 7. Sync standard answers back to legacy columns
        $this->ticket->syncStandardAnswersToColumns();

        session()->flash('success', 'Ticket updated successfully.');
        $this->redirect(route('tickets.show', $this->ticket->LGL_ROW_ID), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
            <flux:icon name="arrow-left" class="h-4 w-4" />
            Back to List
        </a>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Edit Ticket</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Update ticket information. File upload is optional (only if you want to replace documents).</p>
    </div>

    <!-- Form -->
    <form wire:submit="save" class="space-y-6">
        @foreach($this->sections as $sectionIndex => $section)
            @php
                $isFinalization = $section->SECT_CODE === 'finalization';
                // Finalization section only shows for 'done' tickets
                if ($isFinalization && $this->ticket->status?->LOV_VALUE !== 'done') {
                    continue;
                }

                $sectionQuestions = $this->getQuestionsForSection($section);
                $isBasicSection = $section->SECT_CODE === 'basic';
                $answersSource = $isFinalization ? 'finalizationAnswers' : 'dynamicAnswers';

                // Form section only shows when document type is selected and has questions
                if ($section->SECT_CODE === 'form' && $sectionQuestions->count() === 0) {
                    continue;
                }
            @endphp

            <div class="rounded-xl border {{ $isFinalization ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30' : 'border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900' }} p-6" wire:key="section-{{ $section->SECT_CODE }}">
                <h2 class="mb-4 text-lg font-semibold {{ $isFinalization ? 'text-green-900 dark:text-green-300' : 'text-neutral-900 dark:text-white' }}">
                    {{ $sectionIndex + 1 }}. {{ $section->SECT_LABEL }}
                </h2>
                @if($section->SECT_DESCRIPTION)
                    <p class="mb-4 text-sm {{ $isFinalization ? 'text-green-700 dark:text-green-400' : 'text-neutral-500 dark:text-neutral-400' }}">
                        {{ $section->SECT_DESCRIPTION }}
                    </p>
                @endif

                <div class="grid gap-4 {{ $isBasicSection || $section->SECT_CODE === 'form' ? 'sm:grid-cols-2' : '' }}">
                    {{-- Division & Department (read-only display) --}}
                    @if($isBasicSection)
                        <flux:field>
                            <flux:label>User Directorate (Division)</flux:label>
                            <flux:input :value="$this->ticketDivision" disabled />
                            <flux:description>Set by the ticket creator's account.</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Department</flux:label>
                            <flux:input :value="$this->ticketDepartment" disabled />
                            <flux:description>Set by the ticket creator's account.</flux:description>
                        </flux:field>
                    @endif

                    {{-- Dynamic questions for this section --}}
                    @foreach($sectionQuestions as $question)
                        @if($this->isDependencyMet($question, $answersSource))
                        @php
                            $isEditable = (bool) $question->QUEST_IS_EDITABLE;
                        @endphp
                        <flux:field class="{{ $question->QUEST_WIDTH === 'full' ? 'sm:col-span-2' : '' }}" wire:key="q-{{ $section->SECT_CODE }}-{{ $question->QUEST_CODE }}">
                            <flux:label>
                                {{ $question->QUEST_LABEL }}
                                @if($question->QUEST_IS_REQUIRED)<span class="text-red-500">*</span>@endif
                                @if(! $isEditable)<span class="ml-1 text-xs text-neutral-400">(read-only)</span>@endif
                            </flux:label>

                            @if($question->QUEST_TYPE === 'boolean')
                                <flux:radio.group wire:model.live="{{ $answersSource }}.{{ $question->QUEST_CODE }}" variant="segmented" :disabled="!$isEditable">
                                    <flux:radio value="1" label="Yes" />
                                    <flux:radio value="0" label="No" />
                                </flux:radio.group>
                            @elseif($question->QUEST_TYPE === 'select')
                                <flux:radio.group wire:model.live="{{ $answersSource }}.{{ $question->QUEST_CODE }}" variant="segmented" :disabled="!$isEditable">
                                    @foreach($question->QUEST_OPTIONS ?? [] as $opt)
                                    <flux:radio value="{{ $opt['value'] }}" label="{{ $opt['label'] }}" />
                                    @endforeach
                                </flux:radio.group>
                            @elseif($question->QUEST_TYPE === 'date')
                                <flux:input type="date" wire:model="{{ $answersSource }}.{{ $question->QUEST_CODE }}" :disabled="!$isEditable" />
                            @elseif($question->QUEST_TYPE === 'number')
                                <flux:input type="number" wire:model="{{ $answersSource }}.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" :disabled="!$isEditable" />
                            @elseif($question->QUEST_TYPE === 'textarea')
                                <flux:textarea wire:model="{{ $answersSource }}.{{ $question->QUEST_CODE }}" rows="3" :placeholder="$question->QUEST_PLACEHOLDER" :disabled="!$isEditable" />
                            @elseif($question->QUEST_TYPE === 'file')
                                <div class="space-y-3">
                                    {{-- List Existing Files --}}
                                    @php
                                        $existing = $this->{$answersSource}[$question->QUEST_CODE] ?? null;
                                        
                                        $filePaths = [];
                                        if ($existing) {
                                            $decoded = json_decode($existing, true);
                                            // Handle both JSON array and plain string formats
                                            $rawFiles = is_array($decoded) ? $decoded : [$existing];
                                            
                                            // Normalize: support both string paths and legacy {name, path} objects
                                            $filePaths = collect($rawFiles)->map(fn ($f) => is_array($f) ? ($f['path'] ?? '') : $f)->filter()->values()->all();
                                        }
                                    @endphp

                                    @if (! empty($filePaths))
                                        <div class="space-y-2">
                                            <p class="text-sm font-medium text-zinc-500">Existing Files:</p>
                                            @foreach ($filePaths as $path)
                                                <div class="flex items-center justify-between p-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                                    @php
                                                        $filename = basename($path);
                                                        $previewUrl = route('tickets.documents.preview', ['ticketNumber' => $this->ticket->TCKT_NO, 'path' => $filename]);
                                                    @endphp
                                                    <a href="{{ $previewUrl }}" target="_blank" class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                                        <flux:icon.document class="size-4" />
                                                        <span>{{ $filename }}</span>
                                                    </a>
                                                    @if($isEditable)
                                                        <flux:button variant="ghost" size="sm" icon="trash" 
                                                            wire:click="removeFile('{{ $question->QUEST_CODE }}', '{{ $path }}')"
                                                            wire:confirm="Are you sure you want to remove this file?" />
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($isEditable)
                                        <input type="file" wire:model="dynamicFiles.{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} {{ $question->QUEST_IS_MULTIPLE ? 'multiple' : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                                        <div wire:loading wire:target="dynamicFiles.{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-blue-600">Uploading...</div>
                                    @else
                                        <p class="text-sm text-neutral-400">This file cannot be changed.</p>
                                    @endif
                                </div>
                            @else
                                <flux:input wire:model="{{ $answersSource }}.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" :disabled="!$isEditable" />
                            @endif

                            @if($question->QUEST_DESCRIPTION)
                                <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                            @endif
                            <flux:error name="{{ $answersSource }}.{{ $question->QUEST_CODE }}" />
                            <flux:error name="dynamicFiles.{{ $question->QUEST_CODE }}" />
                        </flux:field>
                        @endif
                    @endforeach

                    {{-- Document Type selector at end of basic section --}}
                    @if($isBasicSection)
                        <flux:field class="sm:col-span-2">
                            <flux:label>Document Type <span class="text-red-500">*</span></flux:label>
                            <flux:select wire:model.live="document_type" required>
                                <option value="">Select Document Type</option>
                                @foreach($this->documentTypes as $docType)
                                <option value="{{ $docType->CODE }}">{{ $docType->REF_DOC_TYPE_NAME }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="document_type" />
                        </flux:field>
                    @endif
                </div>
            </div>
        @endforeach

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tickets.index') }}" wire:navigate>
                <flux:button variant="ghost">Cancel</flux:button>
            </a>
            <flux:button type="submit" variant="primary">
                Update Ticket
            </flux:button>
        </div>
    </form>
</div>

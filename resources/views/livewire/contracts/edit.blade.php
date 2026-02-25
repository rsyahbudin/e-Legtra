<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\FormQuestion;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    // Structural fields
    public $division_id;

    public $department_id;

    public string $document_type = '';

    // Dynamic answers (keyed by question code) — covers basic, form, supporting, finalization
    public array $dynamicAnswers = [];

    // Finalization answers (keyed by question code)
    public array $finalizationAnswers = [];

    // Dynamic file uploads
    public $dynamicFiles_draft_document;

    public $dynamicFiles_mandatory_documents = [];

    public $dynamicFiles_approval_document;

    public function mount(int $contract): void
    {
        $this->ticket = Ticket::with(['answers.question'])->findOrFail($contract);

        // Populate structural fields
        $this->division_id = $this->ticket->DIV_ID;
        $this->department_id = $this->ticket->DEPT_ID;
        $this->document_type = $this->ticket->documentType?->code ?? '';

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
                // basic, form, supporting (non-file) all go into dynamicAnswers
                $this->dynamicAnswers[$code] = $answer->ANS_VALUE;
            }
        }
    }

    /**
     * Get "basic" section questions.
     */
    public function getBasicQuestionsProperty()
    {
        return FormQuestion::active()
            ->forSection('basic')
            ->forDocType(null)
            ->ordered()
            ->get();
    }

    /**
     * Get doc-type-specific "form" questions.
     */
    public function getFormQuestionsProperty()
    {
        if (! $this->document_type) {
            return collect();
        }

        $docTypeId = DocumentType::getIdByCode($this->document_type);

        return FormQuestion::active()
            ->forSection('form')
            ->forDocType($docTypeId)
            ->ordered()
            ->get();
    }

    /**
     * Get "supporting" section questions.
     */
    public function getSupportingQuestionsProperty()
    {
        return FormQuestion::active()
            ->forSection('supporting')
            ->forDocType(null)
            ->ordered()
            ->get();
    }

    /**
     * Get finalization questions for the current document type.
     */
    public function getFinalizationQuestionsProperty()
    {
        $docTypeId = $this->ticket->TCKT_DOC_TYPE_ID;

        return FormQuestion::active()
            ->forSection('finalization')
            ->forDocType($docTypeId)
            ->ordered()
            ->get();
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('REF_DIV_NAME')->get();
    }

    public function getDepartmentsProperty()
    {
        if (! $this->division_id) {
            return collect();
        }

        return Department::where('DIV_ID', $this->division_id)->orderBy('REF_DEPT_NAME')->get();
    }

    public function getDocumentTypesProperty()
    {
        return DocumentType::active()->get();
    }

    /**
     * Check if a dependent question should be visible.
     */
    public function isDependencyMet(FormQuestion $question, string $section = 'form'): bool
    {
        if (! $question->QUEST_DEPENDS_ON) {
            return true;
        }

        $answers = $section === 'finalization' ? $this->finalizationAnswers : $this->dynamicAnswers;
        $parentValue = $answers[$question->QUEST_DEPENDS_ON] ?? null;

        return (string) $parentValue === (string) $question->QUEST_DEPENDS_VALUE;
    }

    public function save(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only legal team can edit tickets.');

            return;
        }

        // Base validation rules (only structural)
        $rules = [
            'document_type' => ['required', Rule::in(DocumentType::active()->pluck('code')->toArray())],
        ];

        // Dynamic question validation for all sections
        $allQuestions = collect()
            ->merge($this->basicQuestions)
            ->merge($this->formQuestions)
            ->merge($this->supportingQuestions);

        foreach ($allQuestions as $question) {
            if ($question->QUEST_TYPE === 'file') {
                continue;
            }

            if (! $this->isDependencyMet($question, $question->QUEST_SECTION)) {
                continue;
            }

            $fieldRules = [];
            $fieldRules[] = $question->QUEST_IS_REQUIRED ? 'required' : 'nullable';

            match ($question->QUEST_TYPE) {
                'text' => $fieldRules[] = 'string',
                'number' => $fieldRules[] = 'numeric',
                'date' => $fieldRules[] = 'date',
                'boolean' => $fieldRules[] = 'in:0,1',
                'select' => $fieldRules[] = 'string',
                default => null,
            };

            $rules["dynamicAnswers.{$question->QUEST_CODE}"] = $fieldRules;
        }

        // Finalization question validation (for done status)
        if ($this->ticket->status?->LOV_VALUE === 'done' && $this->finalizationQuestions->count() > 0) {
            foreach ($this->finalizationQuestions as $question) {
                if (! $this->isDependencyMet($question, 'finalization')) {
                    continue;
                }

                $fieldRules = [];
                $fieldRules[] = $question->QUEST_IS_REQUIRED ? 'required' : 'nullable';

                match ($question->QUEST_TYPE) {
                    'text' => $fieldRules[] = 'string',
                    'boolean' => $fieldRules[] = 'in:0,1',
                    default => null,
                };

                $rules["finalizationAnswers.{$question->QUEST_CODE}"] = $fieldRules;
            }
        }

        // File validation
        $rules['dynamicFiles_draft_document'] = ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'];
        $rules['dynamicFiles_mandatory_documents.*'] = ['nullable', 'file', 'max:10240'];
        $rules['dynamicFiles_approval_document'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];

        $this->validate($rules);

        // Update ticket structural fields only
        $this->ticket->update([
            'DIV_ID' => $this->division_id,
            'DEPT_ID' => $this->department_id,
            'TCKT_DOC_TYPE_ID' => DocumentType::getIdByCode($this->document_type),
        ]);

        // Save/update dynamic answers for all non-file sections
        $this->saveAnswersForSection($allQuestions->filter(fn ($q) => $q->QUEST_TYPE !== 'file'), $this->dynamicAnswers);

        // Save/update finalization answers (if applicable)
        if ($this->ticket->status?->LOV_VALUE === 'done' && $this->finalizationQuestions->count() > 0) {
            $this->saveAnswersForSection($this->finalizationQuestions, $this->finalizationAnswers);
        }

        // Handle file uploads — store paths as TicketAnswer values
        if ($this->dynamicFiles_draft_document) {
            $this->saveFileAnswer('draft_document', $this->dynamicFiles_draft_document);
        }

        if ($this->dynamicFiles_mandatory_documents && count($this->dynamicFiles_mandatory_documents) > 0) {
            $this->saveMultipleFileAnswer('mandatory_documents', $this->dynamicFiles_mandatory_documents);
        }

        if ($this->dynamicFiles_approval_document) {
            $this->saveFileAnswer('approval_document', $this->dynamicFiles_approval_document);
        }

        // Log activity
        $this->ticket->activityLogs()->create([
            'LOG_CAUSER_ID' => $user->LGL_ROW_ID,
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'status_change',
            'LOG_DESC' => 'Updated ticket details',
            'LOG_PROPERTIES' => [
                'ticket_number' => $this->ticket->TCKT_NO,
                'status' => $this->ticket->status?->LOV_VALUE,
                'updated_by' => $user->name,
            ],
            'LOG_NAME' => 'ticket_activity',
        ]);

        session()->flash('success', 'Ticket updated successfully.');
        $this->redirect(route('tickets.show', $this->ticket->LGL_ROW_ID), navigate: true);
    }

    /**
     * Save/update answers for a given set of questions.
     */
    private function saveAnswersForSection($questions, array $answers): void
    {
        foreach ($questions as $question) {
            $value = $answers[$question->QUEST_CODE] ?? null;

            if ($value === null || $value === '') {
                // Delete existing answer if cleared
                TicketAnswer::where('ANS_TICKET_ID', $this->ticket->LGL_ROW_ID)
                    ->where('ANS_QUESTION_ID', $question->LGL_ROW_ID)
                    ->delete();

                continue;
            }

            TicketAnswer::updateOrCreate(
                [
                    'ANS_TICKET_ID' => $this->ticket->LGL_ROW_ID,
                    'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
                ],
                ['ANS_VALUE' => (string) $value]
            );
        }
    }

    /**
     * Save a single file upload as a TicketAnswer.
     */
    private function saveFileAnswer(string $questionCode, $file): void
    {
        $question = FormQuestion::where('QUEST_CODE', $questionCode)->first();
        if (! $question) {
            return;
        }

        $path = $file->store("tickets/{$this->ticket->LGL_ROW_ID}/{$questionCode}", 'public');

        TicketAnswer::updateOrCreate(
            [
                'ANS_TICKET_ID' => $this->ticket->LGL_ROW_ID,
                'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
            ],
            ['ANS_VALUE' => $path]
        );
    }

    /**
     * Save multiple file uploads as a JSON TicketAnswer (appends to existing).
     */
    private function saveMultipleFileAnswer(string $questionCode, $files): void
    {
        $question = FormQuestion::where('QUEST_CODE', $questionCode)->first();
        if (! $question) {
            return;
        }

        // Get existing paths
        $existingAnswer = TicketAnswer::where('ANS_TICKET_ID', $this->ticket->LGL_ROW_ID)
            ->where('ANS_QUESTION_ID', $question->LGL_ROW_ID)
            ->first();

        $paths = $existingAnswer ? json_decode($existingAnswer->ANS_VALUE, true) ?? [] : [];

        foreach ($files as $file) {
            $paths[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store("tickets/{$this->ticket->LGL_ROW_ID}/{$questionCode}", 'public'),
            ];
        }

        TicketAnswer::updateOrCreate(
            [
                'ANS_TICKET_ID' => $this->ticket->LGL_ROW_ID,
                'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
            ],
            ['ANS_VALUE' => json_encode($paths)]
        );
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
            <flux:icon name="arrow-left" class="h-4 w-4" />
            ← Back to List
        </a>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Edit Ticket</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Update ticket information. File upload is optional (only if you want to replace documents).</p>
    </div>

    <!-- Form -->
    <form wire:submit="save" class="space-y-6">
        <!-- 1. Basic Information -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">1. Basic Information</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Division (structural) -->
                <flux:field>
                    <flux:label>User Directorate (Division)</flux:label>
                    <flux:select wire:model="division_id" name="division_id">
                        <option value="">-- Select Division --</option>
                        @foreach($this->divisions as $division)
                        <option value="{{ $division->LGL_ROW_ID }}">{{ $division->REF_DIV_NAME }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="division_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:select wire:model="department_id" name="department_id">
                        <option value="">-- Select Department --</option>
                        @foreach($this->departments as $dept)
                        <option value="{{ $dept->LGL_ROW_ID }}">{{ $dept->REF_DEPT_NAME }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="department_id" />
                </flux:field>

                <!-- Dynamic basic questions -->
                @foreach($this->basicQuestions as $question)
                    @if($this->isDependencyMet($question, 'basic'))
                    <flux:field class="{{ $question->QUEST_WIDTH === 'full' ? 'sm:col-span-2' : '' }}" wire:key="basic-{{ $question->QUEST_CODE }}">
                        <flux:label>{{ $question->QUEST_LABEL }} @if($question->QUEST_IS_REQUIRED)<span class="text-red-500">*</span>@endif</flux:label>
                        
                        @if($question->QUEST_TYPE === 'boolean')
                            <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented">
                                <flux:radio value="1" label="Yes" />
                                <flux:radio value="0" label="No" />
                            </flux:radio.group>
                        @elseif($question->QUEST_TYPE === 'select')
                            <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented">
                                @foreach($question->QUEST_OPTIONS ?? [] as $opt)
                                <flux:radio value="{{ $opt['value'] }}" label="{{ $opt['label'] }}" />
                                @endforeach
                            </flux:radio.group>
                        @elseif($question->QUEST_TYPE === 'file')
                            @php
                                $existingFile = $this->dynamicAnswers[$question->QUEST_CODE] ?? null;
                            @endphp
                            @if($existingFile)
                            <div class="mb-2 text-sm text-green-600 dark:text-green-400">
                                ✓ File already uploaded. Upload a new file to replace.
                            </div>
                            @endif
                            <input type="file" wire:model="dynamicFiles_{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} {{ $question->QUEST_IS_MULTIPLE ? 'multiple' : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                            <div wire:loading wire:target="dynamicFiles_{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-blue-600">Uploading...</div>
                        @else
                            <flux:input wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                        @endif

                        @if($question->QUEST_DESCRIPTION)
                            <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                        @endif
                        <flux:error name="dynamicAnswers.{{ $question->QUEST_CODE }}" />
                    </flux:field>
                    @endif
                @endforeach

                <!-- Document Type (structural) -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Document Type *</flux:label>
                    <flux:select wire:model.live="document_type" required>
                        <option value="">Select Document Type</option>
                        @foreach($this->documentTypes as $docType)
                        <option value="{{ $docType->code }}">{{ $docType->REF_DOC_TYPE_NAME }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="document_type" />
                </flux:field>
            </div>
        </div>

        <!-- 2. Document Details -->
        @if($this->formQuestions->count() > 0)
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">2. Document Details</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($this->formQuestions as $question)
                    @if($this->isDependencyMet($question))
                    <flux:field class="{{ $question->QUEST_WIDTH === 'full' ? 'sm:col-span-2' : '' }}" wire:key="form-{{ $question->QUEST_CODE }}">
                        <flux:label>{{ $question->QUEST_LABEL }} @if($question->QUEST_IS_REQUIRED)<span class="text-red-500">*</span>@endif</flux:label>
                        
                        @if($question->QUEST_TYPE === 'boolean')
                            <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented">
                                <flux:radio value="1" label="Yes" />
                                <flux:radio value="0" label="No" />
                            </flux:radio.group>
                        @elseif($question->QUEST_TYPE === 'date')
                            <flux:input type="date" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" />
                        @elseif($question->QUEST_TYPE === 'number')
                            <flux:input type="number" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                        @else
                            <flux:input wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                        @endif

                        @if($question->QUEST_DESCRIPTION)
                            <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                        @endif
                        <flux:error name="dynamicAnswers.{{ $question->QUEST_CODE }}" />
                    </flux:field>
                    @endif
                @endforeach
            </div>
        </div>
        @endif

        <!-- 3. Supporting Documents -->
        @if($this->document_type)
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">{{ $this->formQuestions->count() > 0 ? '3' : '2' }}. Supporting Documents</h2>
            
            <div class="grid gap-4">
                @foreach($this->supportingQuestions as $question)
                    @if($this->isDependencyMet($question, 'supporting'))
                    <flux:field wire:key="support-{{ $question->QUEST_CODE }}">
                        <flux:label>{{ $question->QUEST_LABEL }} @if($question->QUEST_IS_REQUIRED)<span class="text-red-500">*</span>@endif</flux:label>
                        
                        @if($question->QUEST_TYPE === 'boolean')
                            <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented">
                                <flux:radio value="1" label="Yes" />
                                <flux:radio value="0" label="No" />
                            </flux:radio.group>
                        @elseif($question->QUEST_TYPE === 'file')
                            @php
                                $existingFile = $this->dynamicAnswers[$question->QUEST_CODE] ?? null;
                            @endphp
                            @if($existingFile)
                            <div class="mb-2 text-sm text-green-600 dark:text-green-400">
                                ✓ File(s) already uploaded. Upload new file(s) to {{ $question->QUEST_IS_MULTIPLE ? 'add more' : 'replace' }}.
                            </div>
                            @endif
                            <input type="file" wire:model="dynamicFiles_{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} {{ $question->QUEST_IS_MULTIPLE ? 'multiple' : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-purple-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-purple-700 hover:file:bg-purple-100 dark:text-neutral-400 dark:file:bg-purple-900/30 dark:file:text-purple-400" />
                            <div wire:loading wire:target="dynamicFiles_{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-purple-600">Uploading...</div>
                        @else
                            <flux:input wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                        @endif

                        @if($question->QUEST_DESCRIPTION)
                            <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                        @endif
                        <flux:error name="dynamicFiles_{{ $question->QUEST_CODE }}" />
                    </flux:field>
                    @endif
                @endforeach
            </div>
        </div>
        @endif

        {{-- Finalization Checklist Section (for done status only) --}}
        @if($ticket->status?->LOV_VALUE === 'done' && $this->finalizationQuestions->count() > 0)
        <div class="rounded-xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950/30">
            <h2 class="mb-4 text-lg font-semibold text-green-900 dark:text-green-300">Finalization Checklist</h2>
            <p class="mb-4 text-sm text-green-700 dark:text-green-400">Answers to finalization questions. You can update them if needed.</p>
            
            <div class="space-y-4">
                @foreach($this->finalizationQuestions as $index => $question)
                    @if($this->isDependencyMet($question, 'finalization'))
                    <flux:field wire:key="fin-{{ $question->QUEST_CODE }}">
                        <flux:label>{{ $index + 1 }}. {{ $question->QUEST_LABEL }} @if($question->QUEST_IS_REQUIRED)<span class="text-red-500">*</span>@endif</flux:label>
                        
                        @if($question->QUEST_TYPE === 'boolean')
                            <flux:radio.group wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" variant="segmented">
                                <flux:radio value="1" label="Yes" />
                                <flux:radio value="0" label="No" />
                            </flux:radio.group>
                        @elseif($question->QUEST_TYPE === 'text')
                            <flux:textarea wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" rows="3" :placeholder="$question->QUEST_PLACEHOLDER" />
                        @else
                            <flux:input wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                        @endif

                        @if($question->QUEST_DESCRIPTION)
                            <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                        @endif
                        <flux:error name="finalizationAnswers.{{ $question->QUEST_CODE }}" />
                    </flux:field>
                    @endif
                @endforeach
            </div>
        </div>
        @endif

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

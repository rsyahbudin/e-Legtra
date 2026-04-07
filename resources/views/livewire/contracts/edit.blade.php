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
    public array $dynamicFiles = [];

    public function mount(int $contract): void
    {
        $this->ticket = Ticket::with(['answers.question'])->findOrFail($contract);

        // Populate structural fields
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
            'document_type' => ['required', Rule::in(DocumentType::active()->pluck('CODE')->toArray())],
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

            if ($question->QUEST_TYPE === 'file') {
                $path = "dynamicFiles.{$question->QUEST_CODE}";
                if ($question->QUEST_IS_MULTIPLE) {
                    $path .= '.*';
                }
                $fieldRules['type'] = 'file';
                if ($question->QUEST_MAX_SIZE_KB) {
                    $fieldRules[] = 'max:' . $question->QUEST_MAX_SIZE_KB;
                }
                if ($question->QUEST_ACCEPT) {
                    $mimes = str_replace('.', '', $question->QUEST_ACCEPT); // e.g. .pdf,.doc -> pdf,doc
                    $fieldRules[] = 'mimes:' . $mimes;
                }
                $rules[$path] = array_values($fieldRules); // Re-index for Laravel validation
                continue;
            }

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

                if ($question->QUEST_TYPE === 'file') {
                    $path = "dynamicFiles.{$question->QUEST_CODE}";
                    if ($question->QUEST_IS_MULTIPLE) {
                        $path .= '.*';
                    }
                    $fieldRules['type'] = 'file';
                    if ($question->QUEST_MAX_SIZE_KB) {
                        $fieldRules[] = 'max:' . $question->QUEST_MAX_SIZE_KB;
                    }
                    if ($question->QUEST_ACCEPT) {
                        $mimes = str_replace('.', '', $question->QUEST_ACCEPT);
                        $fieldRules[] = 'mimes:' . $mimes;
                    }
                    $rules[$path] = array_values($fieldRules);
                    continue;
                }

                match ($question->QUEST_TYPE) {
                    'text' => $fieldRules[] = 'string',
                    'boolean' => $fieldRules[] = 'in:0,1',
                    default => null,
                };

                $rules["finalizationAnswers.{$question->QUEST_CODE}"] = $fieldRules;
            }
        }

        // Save/update ALL file answers dynamically based on FormQuestion
        $fileQuestions = FormQuestion::where('QUEST_TYPE', 'file')->get();

        foreach ($fileQuestions as $question) {
            $files = $this->dynamicFiles[$question->QUEST_CODE] ?? null;
            if (! $files) {
                continue;
            }

            $category = ($question->QUEST_SECTION === 'finalization') ? 'legal' : 'request';

            if ($question->QUEST_IS_MULTIPLE && is_array($files)) {
                $this->saveMultipleFileAnswer($question->QUEST_CODE, $files);
            } elseif ($files instanceof \Illuminate\Http\UploadedFile) {
                $this->saveFileAnswer($question->QUEST_CODE, $files, $category);
            }
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

        // Sync standard answers back to legacy Ticket master fields
        $this->ticket->syncStandardAnswersToColumns();

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
    private function saveFileAnswer(string $questionCode, $file, string $category = 'request'): void
    {
        $question = FormQuestion::where('QUEST_CODE', $questionCode)->first();
        if (! $question) {
            return;
        }

        // Use legal_docs disk and TCKT_NO path
        $path = $file->store("{$this->ticket->TCKT_NO}/{$category}", 'legal_docs');

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
                'path' => $file->store("{$this->ticket->TCKT_NO}/request", 'legal_docs'),
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

    /**
     * Remove a specific file from a file answer.
     */
    public function removeFile(string $questionCode, int $index = -1): void
    {
        $question = FormQuestion::where('QUEST_CODE', $questionCode)->first();
        if (! $question) {
            return;
        }

        $answer = TicketAnswer::where('ANS_TICKET_ID', $this->ticket->LGL_ROW_ID)
            ->where('ANS_QUESTION_ID', $question->LGL_ROW_ID)
            ->first();

        if ($answer) {
            if ($question->QUEST_IS_MULTIPLE && $index !== -1) {
                $paths = json_decode($answer->ANS_VALUE, true) ?? [];
                if (isset($paths[$index])) {
                    unset($paths[$index]);
                    if (count($paths) > 0) {
                        $answer->update(['ANS_VALUE' => json_encode(array_values($paths))]);
                    } else {
                        $answer->delete();
                    }
                }
            } else {
                $answer->delete();
            }

            // Re-fetch dynamic answers so the UI updates
            $this->mount($this->ticket->LGL_ROW_ID);
        }
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
                    <flux:select wire:model="division_id" name="division_id" disabled>
                        <option value="">-- Select Division --</option>
                        @foreach($this->divisions as $division)
                        <option value="{{ $division->LGL_ROW_ID }}">{{ $division->REF_DIV_NAME }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="division_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:select wire:model="department_id" name="department_id" disabled>
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
                            <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }}>
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
                            @if($question->QUEST_IS_EDITABLE)
                            <input type="file" wire:model="dynamicFiles.{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} {{ $question->QUEST_IS_MULTIPLE ? 'multiple' : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                            <div wire:loading wire:target="dynamicFiles.{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-blue-600">Uploading...</div>
                            @endif
                        @else
                            <flux:input wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }} />
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
                        <option value="{{ $docType->CODE }}">{{ $docType->REF_DOC_TYPE_NAME }}</option>
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
                            <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }}>
                                <flux:radio value="1" label="Yes" />
                                <flux:radio value="0" label="No" />
                            </flux:radio.group>
                        @elseif($question->QUEST_TYPE === 'date')
                            <flux:input type="date" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }} />
                        @elseif($question->QUEST_TYPE === 'number')
                            <flux:input type="number" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }} />
                        @else
                            <flux:input wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }} />
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
                                @if($question->QUEST_IS_MULTIPLE)
                                    @php $files = json_decode($existingFile, true) ?? []; @endphp
                                    @if(count($files) > 0)
                                    <div class="mb-3 space-y-2">
                                        <p class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">Existing Uploads:</p>
                                        @foreach($files as $idx => $f)
                                        @php $fPath = is_array($f) ? $f['path'] : $f; $fName = is_array($f) ? ($f['name'] ?? basename($fPath)) : basename($fPath); @endphp
                                        <div class="flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-800">
                                            <a href="{{ Storage::disk('legal_docs')->url($fPath) }}" target="_blank" class="truncate text-blue-600 hover:underline dark:text-blue-400">
                                                {{ $fName }}
                                            </a>
                                            <button type="button" wire:click="removeFile('{{ $question->QUEST_CODE }}', {{ $idx }})" class="ml-3 text-xs font-medium text-red-500 hover:text-red-700">Remove</button>
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="mb-2 text-xs text-neutral-500">Upload new files below to append them to the list.</div>
                                    @endif
                                @else
                                    <div class="mb-3 flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-zinc-800">
                                        <a href="{{ Storage::disk('legal_docs')->url($existingFile) }}" target="_blank" class="truncate text-blue-600 hover:underline dark:text-blue-400">
                                            Current File
                                        </a>
                                        <button type="button" wire:click="removeFile('{{ $question->QUEST_CODE }}')" class="ml-3 text-xs font-medium text-red-500 hover:text-red-700">Remove</button>
                                    </div>
                                    <div class="mb-2 text-xs text-neutral-500">Upload a new file below to replace it.</div>
                                @endif
                            @endif
                            <input type="file" wire:model="dynamicFiles.{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} {{ $question->QUEST_IS_MULTIPLE ? 'multiple' : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-purple-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-purple-700 hover:file:bg-purple-100 dark:text-neutral-400 dark:file:bg-purple-900/30 dark:file:text-purple-400" />
                            <div wire:loading wire:target="dynamicFiles.{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-purple-600">Uploading...</div>
                        @else
                            <flux:input wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                        @endif

                        @if($question->QUEST_DESCRIPTION)
                            <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                        @endif
                        <flux:error name="dynamicFiles.{{ $question->QUEST_CODE }}" />
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
                            <flux:radio.group wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" variant="segmented" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }}>
                                <flux:radio value="1" label="Yes" />
                                <flux:radio value="0" label="No" />
                            </flux:radio.group>
                        @elseif($question->QUEST_TYPE === 'text')
                            <flux:textarea wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" rows="3" :placeholder="$question->QUEST_PLACEHOLDER" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }} />
                        @elseif($question->QUEST_TYPE === 'file')
                            @php
                                $existingFile = $this->finalizationAnswers[$question->QUEST_CODE] ?? null;
                            @endphp
                            @if($existingFile)
                                @if($question->QUEST_IS_MULTIPLE)
                                    @php $files = json_decode($existingFile, true) ?? []; @endphp
                                    @if(count($files) > 0)
                                    <div class="mb-3 space-y-2">
                                        <p class="text-xs font-semibold text-green-800 dark:text-green-300">Existing Uploads:</p>
                                        @foreach($files as $idx => $f)
                                        @php $fPath = is_array($f) ? $f['path'] : $f; $fName = is_array($f) ? ($f['name'] ?? basename($fPath)) : basename($fPath); @endphp
                                        <div class="flex items-center justify-between rounded-lg border border-green-200 bg-white px-3 py-2 text-sm">
                                            <a href="{{ Storage::disk('legal_docs')->url($fPath) }}" target="_blank" class="truncate text-blue-600 hover:underline">
                                                {{ $fName }}
                                            </a>
                                            @if($question->QUEST_IS_EDITABLE)
                                            <button type="button" wire:click="removeFile('{{ $question->QUEST_CODE }}', {{ $idx }})" class="ml-3 text-xs font-medium text-red-500 hover:text-red-700">Remove</button>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                    @if($question->QUEST_IS_EDITABLE)
                                    <div class="mb-2 text-xs text-green-700">Upload new files below to append them to the list.</div>
                                    @endif
                                    @endif
                                @else
                                    <div class="mb-3 flex items-center justify-between rounded-lg border border-green-200 bg-white px-3 py-2 text-sm">
                                        <a href="{{ Storage::disk('legal_docs')->url($existingFile) }}" target="_blank" class="truncate text-blue-600 hover:underline">
                                            {{ basename($existingFile) }}
                                        </a>
                                        @if($question->QUEST_IS_EDITABLE)
                                        <button type="button" wire:click="removeFile('{{ $question->QUEST_CODE }}')" class="ml-3 text-xs font-medium text-red-500 hover:text-red-700">Remove</button>
                                        @endif
                                    </div>
                                    @if($question->QUEST_IS_EDITABLE)
                                    <div class="mb-2 text-xs text-green-700">Upload a new file below to replace it.</div>
                                    @endif
                                @endif
                            @endif
                            @if($question->QUEST_IS_EDITABLE)
                            <input type="file" wire:model="dynamicFiles.{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                            <div wire:loading wire:target="dynamicFiles.{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-blue-600">Uploading...</div>
                            @endif
                        @else
                            <flux:input wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" {{ !$question->QUEST_IS_EDITABLE ? 'disabled' : '' }} />
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

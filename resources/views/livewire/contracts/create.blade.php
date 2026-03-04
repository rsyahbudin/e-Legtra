<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\FormQuestion;
use App\Models\FormSection;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Services\LegalDocumentService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;

    // Structural fields (not dynamic questions)
    public $DIV_ID;

    public $DEPT_ID;

    public string $document_type = '';

    // Dynamic answers (keyed by question code) — covers basic, form, supporting
    public array $dynamicAnswers = [];

    // Dynamic file uploads (keyed by question code)
    // Uses an array so new file-type questions need zero code changes.
    public array $dynamicFiles = [];

    public function mount(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user->hasPermission('tickets.create')) {
            abort(403, 'You do not have permission to create a ticket.');
        }

        $this->DIV_ID = $user->DIV_ID;
        $this->DEPT_ID = $user->DEPT_ID;

        // Initialize all non-file question answers for static sections (basic, supporting)
        foreach ($this->sections as $section) {
            if ($section->SECT_CODE === 'form') {
                continue; // form questions are initialized in updatedDocumentType
            }
            foreach ($this->getQuestionsForSection($section) as $question) {
                if ($question->QUEST_TYPE !== 'file') {
                    $this->dynamicAnswers[$question->QUEST_CODE] = '';
                }
            }
        }
    }

    /**
     * Hook when document_type is updated to initialize form-specific variables.
     */
    public function updatedDocumentType(): void
    {
        foreach ($this->getQuestionsForSection($this->sections->firstWhere('SECT_CODE', 'form')) as $question) {
            if (! isset($this->dynamicAnswers[$question->QUEST_CODE])) {
                $this->dynamicAnswers[$question->QUEST_CODE] = '';
            }
        }
    }

    /**
     * Get all active sections visible on create form, ordered.
     */
    public function getSectionsProperty()
    {
        return FormSection::active()
            ->forCreate()
            ->ordered()
            ->get();
    }

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

        // 'form' section is doc-type-specific
        if ($section->SECT_CODE === 'form') {
            if (! $this->document_type) {
                return collect();
            }
            $docTypeId = DocumentType::getIdByCode($this->document_type);
            $query->forDocType($docTypeId);
        } else {
            $query->forDocType(null);
        }

        return $query->get();
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('REF_DIV_NAME')->get();
    }

    public function getDepartmentsProperty()
    {
        if (! $this->DIV_ID) {
            return collect();
        }

        return Department::where('DIV_ID', $this->DIV_ID)->orderBy('REF_DEPT_NAME')->get();
    }

    public function getDocumentTypesProperty()
    {
        return DocumentType::active()->get();
    }

    /**
     * Check if a dependent question should be visible.
     */
    public function isDependencyMet(FormQuestion $question): bool
    {
        if (! $question->QUEST_DEPENDS_ON) {
            return true;
        }

        $parentValue = $this->dynamicAnswers[$question->QUEST_DEPENDS_ON] ?? null;

        return (string) $parentValue === (string) $question->QUEST_DEPENDS_VALUE;
    }

    public function save()
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();

            if (! $user->hasPermission('tickets.create')) {
                $this->dispatch('notify', type: 'error', message: 'You do not have permission to create a ticket.');

                return;
            }

            // Base validation rules (only structural fields)
            $rules = [
                'DIV_ID' => ['required', 'exists:LGL_DIVISION,LGL_ROW_ID'],
                'DEPT_ID' => ['required', 'exists:LGL_DEPARTMENT,LGL_ROW_ID'],
                'document_type' => ['required', Rule::in(DocumentType::active()->pluck('code')->toArray())],
            ];

            // Dynamic question validation for all sections
            $allQuestions = collect();
            foreach ($this->sections as $section) {
                $allQuestions = $allQuestions->merge($this->getQuestionsForSection($section));
            }

            foreach ($allQuestions as $question) {
                if ($question->QUEST_TYPE === 'file') {
                    // File validation handled separately
                    continue;
                }

                if (! $this->isDependencyMet($question)) {
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

            // Dynamic file field validation — built from FormQuestion metadata
            $fileQuestions = $allQuestions->where('QUEST_TYPE', 'file');
            foreach ($fileQuestions as $fileQuestion) {
                $code = $fileQuestion->QUEST_CODE;
                $fileRules = [];
                $fileRules[] = $fileQuestion->QUEST_IS_REQUIRED ? 'required' : 'nullable';
                $fileRules[] = 'file';

                if ($fileQuestion->QUEST_ACCEPT) {
                    // Convert accept string (e.g. '.pdf,.doc,.docx') to mimes rule
                    $mimes = collect(explode(',', $fileQuestion->QUEST_ACCEPT))
                        ->map(fn ($ext) => ltrim(trim($ext), '.'))
                        ->filter()
                        ->implode(',');
                    if ($mimes) {
                        $fileRules[] = "mimes:{$mimes}";
                    }
                }

                $maxSize = $fileQuestion->QUEST_MAX_SIZE_KB ?? 10240;
                $fileRules[] = "max:{$maxSize}";

                if ($fileQuestion->QUEST_IS_MULTIPLE) {
                    $rules["dynamicFiles.{$code}"] = ['nullable', 'array'];
                    $rules["dynamicFiles.{$code}.*"] = $fileRules;
                } else {
                    $rules["dynamicFiles.{$code}"] = $fileRules;
                }
            }

            $this->validate($rules);

            // Create ticket (only structural columns)
            $ticket = Ticket::create([
                'DIV_ID' => $this->DIV_ID,
                'DEPT_ID' => $this->DEPT_ID,
                'TCKT_DOC_TYPE_ID' => DocumentType::getIdByCode($this->document_type),
                'TCKT_STS_ID' => \App\Models\TicketStatus::getIdByCode('open'),
                'TCKT_CREATED_BY' => $user->LGL_ROW_ID,
            ]);

            // Save ALL dynamic answers (basic + form + supporting non-file)
            foreach ($allQuestions as $question) {
                if ($question->QUEST_TYPE === 'file') {
                    continue;
                }

                $value = $this->dynamicAnswers[$question->QUEST_CODE] ?? null;

                if ($value === null || $value === '') {
                    continue;
                }

                TicketAnswer::create([
                    'ANS_TICKET_ID' => $ticket->LGL_ROW_ID,
                    'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
                    'ANS_VALUE' => (string) $value,
                ]);
            }

            // Handle file uploads dynamically — iterate all file-type questions
            $this->saveFileAnswers($ticket, $allQuestions->where('QUEST_TYPE', 'file'));

            // Send notification
            try {
                app(NotificationService::class)->notifyTicketCreated($ticket);
            } catch (\Exception $notifException) {
                Log::warning('Ticket notification failed but ticket was created', [
                    'ticket_id' => $ticket->LGL_ROW_ID,
                    'error' => $notifException->getMessage(),
                ]);
            }

            session()->flash('success', 'Ticket created successfully and notification sent to legal team.');

            return $this->redirect(route('tickets.index'), navigate: true);

        } catch (\Exception $e) {
            Log::error('Ticket creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', type: 'error', message: 'Failed to create ticket. Please try again.');
        }
    }

    /**
     * Save all file-type question uploads to the legal_docs disk.
     * Handles both single and multiple file uploads dynamically.
     * New file-type questions added to the database require zero code changes.
     */
    private function saveFileAnswers(Ticket $ticket, $fileQuestions): void
    {
        $documentService = app(LegalDocumentService::class);

        foreach ($fileQuestions as $question) {
            $fileData = $this->dynamicFiles[$question->QUEST_CODE] ?? null;

            if (! $fileData) {
                continue;
            }

            if ($question->QUEST_IS_MULTIPLE && is_array($fileData)) {
                // Multiple files — store as JSON array of generated paths (no original names)
                $paths = [];
                foreach ($fileData as $file) {
                    $paths[] = $documentService->uploadDocument($file, $ticket->TCKT_NO, 'request');
                }

                TicketAnswer::create([
                    'ANS_TICKET_ID' => $ticket->LGL_ROW_ID,
                    'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
                    'ANS_VALUE' => json_encode($paths),
                ]);
            } else {
                // Single file
                $path = $documentService->uploadDocument($fileData, $ticket->TCKT_NO, 'request');

                TicketAnswer::create([
                    'ANS_TICKET_ID' => $ticket->LGL_ROW_ID,
                    'ANS_QUESTION_ID' => $question->LGL_ROW_ID,
                    'ANS_VALUE' => $path,
                ]);
            }
        }
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
            <flux:icon name="arrow-left" class="h-4 w-4" />
            Back to List
        </a>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Create New Ticket</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Fill out the form below to create a legal ticket. Questions will appear dynamically based on the selected document type.</p>
    </div>

    <!-- Form -->
    <form wire:submit="save" class="space-y-6">
        @foreach($this->sections as $sectionIndex => $section)
            @php
                $sectionQuestions = $this->getQuestionsForSection($section);
                $isFormSection = $section->SECT_CODE === 'form';
                $isSupportingSection = $section->SECT_CODE === 'supporting';
                $isBasicSection = $section->SECT_CODE === 'basic';

                // Form & supporting sections only show after doc type is selected
                $shouldShow = $isBasicSection || $this->document_type;
                // Form section only shows when it has questions
                if ($isFormSection && $sectionQuestions->count() === 0) {
                    $shouldShow = false;
                }
            @endphp

            @if($shouldShow)
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900" wire:key="section-{{ $section->SECT_CODE }}">
                <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">{{ $sectionIndex + 1 }}. {{ $section->SECT_LABEL }}</h2>
                @if($section->SECT_DESCRIPTION)
                    <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">{{ $section->SECT_DESCRIPTION }}</p>
                @endif

                <div class="grid gap-4 {{ $isSupportingSection ? '' : 'sm:grid-cols-2' }}">
                    {{-- Structural fields only in basic section --}}
                    @if($isBasicSection)
                        <!-- Division (readonly, auto-filled — structural) -->
                        <flux:field>
                            <flux:label>User Directorate (Division)</flux:label>
                            <flux:select wire:model="DIV_ID" name="DIV_ID" disabled>
                                <option value="">-- Select Division --</option>
                                @foreach($this->divisions as $division)
                                <option value="{{ $division->LGL_ROW_ID }}">{{ $division->REF_DIV_NAME }}</option>
                                @endforeach
                            </flux:select>
                            <flux:description>Auto-filled from your account</flux:description>
                            <flux:error name="DIV_ID" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Department</flux:label>
                            <flux:select wire:model="DEPT_ID" name="DEPT_ID" disabled>
                                <option value="">-- Select Department --</option>
                                @foreach($this->departments as $dept)
                                <option value="{{ $dept->LGL_ROW_ID }}">{{ $dept->REF_DEPT_NAME }}</option>
                                @endforeach
                            </flux:select>
                            <flux:description>Auto-filled from your account</flux:description>
                            <flux:error name="DEPT_ID" />
                        </flux:field>
                    @endif

                    {{-- Dynamic questions for this section --}}
                    @foreach($sectionQuestions as $question)
                        @if($this->isDependencyMet($question))
                        <flux:field class="{{ $question->QUEST_WIDTH === 'full' ? 'sm:col-span-2' : '' }}" wire:key="q-{{ $section->SECT_CODE }}-{{ $question->QUEST_CODE }}">
                            <flux:label>{{ $question->QUEST_LABEL }} @if($question->QUEST_IS_REQUIRED)<span class="text-red-500">*</span>@endif</flux:label>

                            @if($question->QUEST_TYPE === 'boolean')
                                <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented">
                                    <flux:radio value="1" label="Yes" />
                                    <flux:radio value="0" label="No" />
                                </flux:radio.group>
                            @elseif($question->QUEST_TYPE === 'select')
                                @if($isBasicSection)
                                    <flux:radio.group wire:model.live="dynamicAnswers.{{ $question->QUEST_CODE }}" variant="segmented">
                                        @foreach($question->QUEST_OPTIONS ?? [] as $opt)
                                        <flux:radio value="{{ $opt['value'] }}" label="{{ $opt['label'] }}" />
                                        @endforeach
                                    </flux:radio.group>
                                @else
                                    <flux:select wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}">
                                        <option value="">Select...</option>
                                        @foreach($question->QUEST_OPTIONS ?? [] as $opt)
                                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            @elseif($question->QUEST_TYPE === 'date')
                                <flux:input type="date" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" />
                            @elseif($question->QUEST_TYPE === 'number')
                                <flux:input type="number" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                            @elseif($question->QUEST_TYPE === 'file')
                                <input type="file" wire:model="dynamicFiles.{{ $question->QUEST_CODE }}" {{ $question->QUEST_ACCEPT ? 'accept='.$question->QUEST_ACCEPT : '' }} {{ $question->QUEST_IS_MULTIPLE ? 'multiple' : '' }} class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                                <div wire:loading wire:target="dynamicFiles.{{ $question->QUEST_CODE }}" class="mt-2 text-sm text-blue-600">Uploading...</div>
                            @else
                                <flux:input wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                            @endif

                            @if($question->QUEST_DESCRIPTION)
                                <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                            @endif
                            <flux:error name="dynamicAnswers.{{ $question->QUEST_CODE }}" />
                            <flux:error name="dynamicFiles.{{ $question->QUEST_CODE }}" />
                        </flux:field>
                        @endif
                    @endforeach

                    {{-- Document Type selector at end of basic section --}}
                    @if($isBasicSection)
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
                    @endif
                </div>
            </div>
            @endif
        @endforeach

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tickets.index') }}" wire:navigate>
                <flux:button variant="ghost">Cancel</flux:button>
            </a>
            <flux:button type="submit" variant="primary">
                Create Ticket
            </flux:button>
        </div>
    </form>
</div>

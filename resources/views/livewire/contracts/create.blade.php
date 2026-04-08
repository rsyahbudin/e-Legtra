<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\FormQuestion;
use App\Models\FormSection;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\LegalDocumentService;
use App\Services\NotificationService;
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

    // Structural fields — auto-set from logged-in user, not editable
    public $DIV_ID;

    public $DEPT_ID;

    public string $document_type = '';

    // Dynamic answers (keyed by question code) — covers basic, form, supporting
    public array $dynamicAnswers = [];

    // Dynamic file uploads (keyed by question code)
    public array $dynamicFiles = [];

    public function mount(): void
    {
        // Clear static permission cache to ensure fresh permissions in tests
        User::flushPermissionsCache();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->hasPermission('tickets.create') && ! $user->hasAnyRole(['super-admin', 'legal'])) {
            abort(403, 'You do not have permission to create a ticket.');
        }

        // Division & Department are locked to the logged-in user
        $this->DIV_ID = $user->DIV_ID;
        $this->DEPT_ID = $user->DEPT_ID;

        // Initialize dynamic answers for doc-type-neutral questions (basic + supporting)
        $questions = FormQuestion::active()
            ->whereIn('QUEST_SECTION', ['basic', 'supporting'])
            ->where(function ($query) {
                $query->whereNull('QUEST_DOC_TYPE_ID')
                      ->orWhere('QUEST_DOC_TYPE_ID', 0);
            })
            ->get();

        foreach ($questions as $question) {
            if ($question->QUEST_TYPE !== 'file') {
                $this->dynamicAnswers[$question->QUEST_CODE] = '';
            }
        }
    }

    /**
     * Hook when document_type changes — initialize form-specific dynamic answers.
     */
    public function updatedDocumentType(): void
    {
        $formSection = $this->sections->firstWhere('SECT_CODE', 'form');
        if (! $formSection) {
            return;
        }

        $formQuestions = $this->getQuestionsForSection($formSection);
        foreach ($formQuestions as $question) {
            if ($question->QUEST_TYPE !== 'file' && ! isset($this->dynamicAnswers[$question->QUEST_CODE])) {
                $this->dynamicAnswers[$question->QUEST_CODE] = '';
            }
        }
    }

    #[Computed]
    public function getSectionsProperty()
    {
        return FormSection::active()
            ->forCreate()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function getDocumentTypesProperty()
    {
        return DocumentType::active()->get();
    }

    /**
     * Get the logged-in user's division name for display.
     */
    #[Computed]
    public function getUserDivisionProperty(): string
    {
        return Division::find($this->DIV_ID)?->REF_DIV_NAME ?? '-';
    }

    /**
     * Get the logged-in user's department name for display.
     */
    #[Computed]
    public function getUserDepartmentProperty(): string
    {
        return Department::find($this->DEPT_ID)?->REF_DEPT_NAME ?? '-';
    }

    public function save(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Check permission - skip for super-admin and legal roles
        if (! $user->hasAnyRole(['super-admin', 'legal']) && ! $user->hasPermission('tickets.create')) {
            $this->dispatch('notify', type: 'error', message: 'You do not have permission to create tickets.');

            return;
        }

        // 1. Collect all questions for validation and labels
        $allQuestions = collect();
        $validationAttributes = [];
        foreach ($this->sections as $section) {
            $sectionQuestions = $this->getQuestionsForSection($section);
            $allQuestions = $allQuestions->merge($sectionQuestions);

            foreach ($sectionQuestions as $question) {
                $validationAttributes["dynamicAnswers.{$question->QUEST_CODE}"] = $question->QUEST_LABEL;
                $validationAttributes["dynamicFiles.{$question->QUEST_CODE}"] = $question->QUEST_LABEL;
                $validationAttributes["dynamicFiles.{$question->QUEST_CODE}.*"] = $question->QUEST_LABEL;
            }
        }

        // 2. Build validation rules
        $rules = [
            'DIV_ID' => ['required', 'exists:LGL_DIVISION,LGL_ROW_ID'],
            'DEPT_ID' => ['required', 'exists:LGL_DEPARTMENT,LGL_ROW_ID'],
            'document_type' => ['required', Rule::in(DocumentType::active()->get()->pluck('CODE')->toArray())],
        ];

        // Combine all dynamic rules
        $rules = array_merge($rules, $this->buildDynamicValidationRules($allQuestions, 'dynamicAnswers', 'dynamicFiles', []));

        $this->validate($rules, [], $validationAttributes);

        try {
            // 3. Create ticket
            $ticket = Ticket::create([
                'DIV_ID' => $this->DIV_ID,
                'DEPT_ID' => $this->DEPT_ID,
                'TCKT_DOC_TYPE_ID' => DocumentType::getIdByCode($this->document_type),
                'TCKT_STS_ID' => TicketStatus::getIdByCode('open'),
                'TCKT_CREATED_BY' => $user->LGL_ROW_ID,
            ]);

            // 4. Save text answers
            $this->saveTextAnswers($ticket, $allQuestions->filter(fn ($q) => $q->QUEST_TYPE !== 'file'), $this->dynamicAnswers);

            // 5. Save file answers
            $this->saveFileAnswers($ticket, $allQuestions->where('QUEST_TYPE', 'file'), $this->dynamicFiles);

            // 6. Notifications
            try {
                app(NotificationService::class)->notifyTicketCreated($ticket);
            } catch (\Exception $notifException) {
                Log::warning('Ticket notification failed but ticket was created', [
                    'ticket_id' => $ticket->LGL_ROW_ID,
                    'error' => $notifException->getMessage(),
                ]);
            }

            // 7. Sync legacy fields
            $ticket->syncStandardAnswersToColumns();

            session()->flash('success', 'Ticket created successfully and notification sent to legal team.');

            $this->redirect(route('tickets.index'), navigate: true);
        } catch (\Exception $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            Log::error('Ticket creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('notify', type: 'error', message: 'Failed to create ticket. Please try again.');
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
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Fill out the form below to submit a new legal request.</p>
    </div>

    <!-- Form -->
    <form wire:submit="save" class="space-y-6">
        @foreach($this->sections as $sectionIndex => $section)
            @php
                $sectionQuestions = $this->getQuestionsForSection($section);
                $isBasicSection = $section->SECT_CODE === 'basic';

                // Form section only shows when document type is selected and has questions
                if ($section->SECT_CODE === 'form' && $sectionQuestions->count() === 0) {
                    continue;
                }
            @endphp

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900" wire:key="section-{{ $section->SECT_CODE }}">
                <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">
                    {{ $sectionIndex + 1 }}. {{ $section->SECT_LABEL }}
                </h2>
                @if($section->SECT_DESCRIPTION)
                    <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">
                        {{ $section->SECT_DESCRIPTION }}
                    </p>
                @endif

                <div class="grid gap-4 {{ $isBasicSection || $section->SECT_CODE === 'form' ? 'sm:grid-cols-2' : '' }}">
                    {{-- Division & Department (read-only, from logged-in user) --}}
                    @if($isBasicSection)
                        <flux:field>
                            <flux:label>User Directorate (Division)</flux:label>
                            <flux:input :value="$this->userDivision" disabled />
                            <flux:description>Automatically set based on your account.</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Department</flux:label>
                            <flux:input :value="$this->userDepartment" disabled />
                            <flux:description>Automatically set based on your account.</flux:description>
                        </flux:field>
                    @endif

                    {{-- Dynamic questions for this section --}}
                    @foreach($sectionQuestions as $question)
                        @if($this->isDependencyMet($question, 'dynamicAnswers'))
                        <flux:field class="{{ $question->QUEST_WIDTH === 'full' ? 'sm:col-span-2' : '' }}" wire:key="q-{{ $section->SECT_CODE }}-{{ $question->QUEST_CODE }}">
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
                            @elseif($question->QUEST_TYPE === 'date')
                                <flux:input type="date" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" />
                            @elseif($question->QUEST_TYPE === 'number')
                                <flux:input type="number" wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" :placeholder="$question->QUEST_PLACEHOLDER" />
                            @elseif($question->QUEST_TYPE === 'textarea')
                                <flux:textarea wire:model="dynamicAnswers.{{ $question->QUEST_CODE }}" rows="3" :placeholder="$question->QUEST_PLACEHOLDER" />
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
                Create Ticket
            </flux:button>
        </div>
    </form>
</div>

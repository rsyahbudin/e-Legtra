<?php

use App\Models\Contract;
use App\Models\FormQuestion;
use App\Models\Ticket;
use App\Models\TicketAnswer;
use App\Services\ContractService;
use App\Services\NotificationService;
use App\Services\TicketService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public Ticket $ticket;

    public bool $showRejectModal = false;

    public bool $showTerminateModal = false;

    public bool $showPreDoneModal = false;

    public string $rejectionReason = '';

    public string $terminationReason = '';

    // Dynamic finalization answers (keyed by question code)
    public array $finalizationAnswers = [];

    public function mount(int $contract): void
    {
        $this->ticket = Ticket::with([
            'division',
            'department',
            'creator',
            'reviewer',
            'contract',
            'contract.status',
            'activityLogs.user',
            'status',
            'documentType',
            'answers.question',
        ])->findOrFail($contract);
    }

    /**
     * Get form questions for document details display.
     */
    public function getFormQuestionsProperty()
    {
        return FormQuestion::active()
            ->forSection('form')
            ->forDocType($this->ticket->TCKT_DOC_TYPE_ID)
            ->ordered()
            ->get();
    }

    /**
     * Get finalization questions for this document type.
     */
    public function getFinalizationQuestionsProperty()
    {
        return FormQuestion::active()
            ->forSection('finalization')
            ->forDocType($this->ticket->TCKT_DOC_TYPE_ID)
            ->ordered()
            ->get();
    }

    /**
     * Check if finalization questions exist for this document type.
     */
    public function getHasFinalizationQuestionsProperty(): bool
    {
        return $this->finalizationQuestions->count() > 0;
    }

    public function moveToOnProcess(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only the legal team can process tickets.');

            return;
        }

        $ticketService = app(TicketService::class);

        if (! $ticketService->canBeReviewed($this->ticket)) {
            $this->dispatch('notify', type: 'error', message: 'Ticket cannot be processed.');

            return;
        }

        $oldStatus = $this->ticket->status?->LOV_VALUE;
        $ticketService->moveToOnProcess($this->ticket, $user);

        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'on_process');

        $this->dispatch('notify', type: 'success', message: 'Ticket successfully moved to On Process status.');

        $this->mount($this->ticket->LGL_ROW_ID);
    }

    public function openRejectModal(): void
    {
        $this->showRejectModal = true;
    }

    public function rejectTicket(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only the legal team can reject tickets.');

            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:10'],
        ]);

        $oldStatus = $this->ticket->status?->LOV_VALUE;
        app(TicketService::class)->reject($this->ticket, $this->rejectionReason, $user);

        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'rejected');

        $this->showRejectModal = false;
        $this->dispatch('notify', type: 'success', message: 'Ticket successfully rejected.');

        $this->mount($this->ticket->LGL_ROW_ID);
    }

    public function openPreDoneModal(): void
    {
        // Pre-populate finalization answers from existing answers
        $this->finalizationAnswers = [];
        foreach ($this->ticket->answers as $answer) {
            if ($answer->question?->QUEST_SECTION === 'finalization') {
                $this->finalizationAnswers[$answer->question->QUEST_CODE] = $answer->ANS_VALUE;
            }
        }

        $this->showPreDoneModal = true;
    }

    public function moveToDone(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only the legal team can complete tickets.');

            return;
        }

        if ($this->ticket->status?->LOV_VALUE !== 'on_process') {
            $this->dispatch('notify', type: 'error', message: 'Only tickets with On Process status can be completed.');

            return;
        }

        // Validate finalization answers if questions exist for this doc type
        if ($this->hasFinalizationQuestions) {
            $rules = [];
            foreach ($this->finalizationQuestions as $question) {
                $fieldRules = [];
                $fieldRules[] = $question->QUEST_IS_REQUIRED ? 'required' : 'nullable';

                match ($question->QUEST_TYPE) {
                    'text' => $fieldRules[] = 'string',
                    'boolean' => $fieldRules[] = 'in:0,1',
                    default => null,
                };

                $rules["finalizationAnswers.{$question->QUEST_CODE}"] = $fieldRules;
            }

            $this->validate($rules);

            // Save finalization answers
            foreach ($this->finalizationQuestions as $question) {
                $value = $this->finalizationAnswers[$question->QUEST_CODE] ?? null;

                if ($value === null || $value === '') {
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

        $ticketService = app(TicketService::class);
        $oldStatus = $this->ticket->status?->LOV_VALUE;
        $ticketService->moveToDone($this->ticket);

        $this->ticket->refresh();

        // Create contract from ticket
        if (! $this->ticket->contract && $this->canCreateContract()) {
            $this->generateContract();
            $this->ticket->load('contract');
        }

        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'done');

        $this->ticket = $this->ticket->fresh([
            'division',
            'department',
            'creator',
            'reviewer',
            'contract.status',
            'activityLogs.user',
        ]);

        $this->showPreDoneModal = false;
        $this->mount($this->ticket->LGL_ROW_ID);
    }

    public function generateContract(): void
    {
        $contractableTypes = ['perjanjian', 'nda', 'surat_kuasa'];

        if (! in_array($this->ticket->documentType?->code, $contractableTypes)) {
            $this->dispatch('notify', type: 'error', message: 'This document type does not require a contract.');

            return;
        }

        if ($this->ticket->status?->LOV_VALUE !== 'done') {
            $this->dispatch('notify', type: 'error', message: 'Ticket must be Done to create a contract.');

            return;
        }

        if ($this->ticket->contract) {
            $this->dispatch('notify', type: 'error', message: 'Contract already created for this ticket.');

            return;
        }

        try {
            $contractService = app(ContractService::class);
            $contract = $contractService->createFromTicket($this->ticket);

            if ($contract->status?->LOV_VALUE === 'expired') {
                $this->ticket->update(['TCKT_STS_ID' => \App\Models\TicketStatus::getIdByCode('closed')]);
                $this->ticket->logActivity('Ticket automatically closed because contract is expired');
                $this->dispatch('notify', type: 'warning', message: "Contract #{$contract->CONTR_NO} created with Expired status. Ticket automatically closed.");
            } else {
                $this->dispatch('notify', type: 'success', message: "Contract #{$contract->CONTR_NO} successfully created.");
            }
        } catch (\Exception $e) {
            Log::error('Contract creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Failed to create contract: '.$e->getMessage());
        }
    }

    public function canCreateContract(): bool
    {
        $contractableTypes = ['perjanjian', 'nda', 'surat_kuasa'];

        return $this->ticket->status?->LOV_VALUE === 'done'
            && ! $this->ticket->contract
            && in_array($this->ticket->documentType?->code, $contractableTypes);
    }

    public function openTerminateModal(): void
    {
        $this->showTerminateModal = true;
    }

    public function terminateContract(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only the legal team can terminate contracts.');

            return;
        }

        if (! $this->ticket->contract) {
            $this->dispatch('notify', type: 'error', message: 'Ticket does not have a contract.');

            return;
        }

        $this->validate([
            'terminationReason' => ['required', 'string', 'min:10'],
        ]);

        $oldStatus = $this->ticket->contract->status?->LOV_VALUE;
        app(ContractService::class)->terminate($this->ticket->contract, $this->terminationReason);

        $notificationService = app(NotificationService::class);
        $notificationService->notifyContractStatusChanged($this->ticket->contract, $oldStatus, 'terminated');

        $this->showTerminateModal = false;
        $this->dispatch('notify', type: 'success', message: 'Contract successfully terminated and ticket closed.');

        $this->mount($this->ticket->LGL_ROW_ID);
    }

    public function moveToClosedDirectly(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Only the legal team can close tickets.');

            return;
        }

        if ($this->ticket->status?->LOV_VALUE !== 'on_process') {
            $this->dispatch('notify', type: 'error', message: 'Only tickets with On Process status can be closed.');

            return;
        }

        $oldStatus = $this->ticket->status?->LOV_VALUE;
        app(TicketService::class)->moveToClosedDirectly($this->ticket);

        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'closed');

        $this->dispatch('notify', type: 'success', message: 'Ticket successfully closed.');

        $this->mount($this->ticket->LGL_ROW_ID);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    <!-- Header with Back Button -->
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
            <flux:icon name="arrow-left" class="h-4 w-4" />
            Back to List
        </a>
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $ticket->TCKT_NO }}</h1>
            </div>
            @php
                $statusBadge = match($ticket->status_color) {
                    'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                    'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                    'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                    'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                    'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
                    default => 'bg-neutral-100 text-neutral-800',
                };
            @endphp
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium {{ $statusBadge }}">
                    {{ $ticket->status_label }}
                </span>
                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium {{ $ticket->aging_display === '-' ? 'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' }}">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $ticket->aging_display }}
                </span>
            </div>
        </div>
    </div>

    <!-- Action Buttons for Legal Team -->
    @if(auth()->user()->hasAnyRole(['super-admin', 'legal']))
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
        <h3 class="mb-3 font-semibold text-blue-900 dark:text-blue-300">Legal Team Actions</h3>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('tickets.edit', $ticket->LGL_ROW_ID) }}" wire:navigate>
                <flux:button variant="ghost" icon="pencil">Edit Ticket</flux:button>
            </a>

            @if($ticket->status?->LOV_VALUE === 'open')
                <flux:button wire:click="moveToOnProcess" variant="primary" icon="play">Process Ticket</flux:button>
                <flux:button wire:click="openRejectModal" variant="danger" icon="x-mark">Reject Ticket</flux:button>
            @elseif($ticket->status?->LOV_VALUE === 'on_process')
                @php
                    $isContractable = $ticket->documentType?->requires_contract;
                @endphp
                
                @if($isContractable)
                    @if($this->hasFinalizationQuestions)
                        <flux:button wire:click="openPreDoneModal" variant="primary" icon="check">
                            Mark as Done (Create Contract)
                        </flux:button>
                    @else
                        <flux:button wire:click="moveToDone" variant="primary" icon="check">
                            Mark as Done (Create Contract)
                        </flux:button>
                    @endif
                @else
                    <flux:button wire:click="moveToClosedDirectly" variant="primary" icon="check-circle">
                        Close Ticket
                    </flux:button>
                @endif
            @endif

            @if($ticket->contract && $ticket->contract->status?->LOV_VALUE === 'active')
                <flux:button wire:click="openTerminateModal" variant="danger" icon="x-circle">Terminate Contract</flux:button>
            @endif
        </div>
    </div>
    @endif

    

<!-- Ticket Information -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Ticket Information</h2>
        
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Ticket Number</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_NO }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Document Type</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->documentType?->REF_DOC_TYPE_NAME ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Division</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->division?->REF_DIV_NAME ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Department</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->department?->REF_DEPT_NAME ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Created By</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->creator?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Created Date</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_CREATED_DT?->format('d M Y H:i') ?? '-' }}</p>
            </div>
            
            @if($ticket->TCKT_REVIEWED_BY)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Reviewed By</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->reviewer?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Review Date</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_REVIEWED_DT?->format('d M Y H:i') ?? '-' }}</p>
            </div>
            @endif

            @if($ticket->TCKT_REJECT_REASON)
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Rejection Reason</p>
                <p class="font-medium text-red-600 dark:text-red-400">{{ $ticket->TCKT_REJECT_REASON }}</p>
            </div>
            @endif
        </div>
    </div>

<!-- Contract Information (if exists) -->
    @if($ticket->contract)
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Contract Information</h2>
        
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Contract Number</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->CONTR_NO }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Contract Status</p>
                @php
                    $color = $ticket->contract->status?->color ?? 'neutral';
                @endphp
                <flux:badge :color="$color" size="sm" inset="top bottom">{{ $ticket->contract->status?->LOV_DISPLAY_NAME ?? 'Unknown' }}</flux:badge>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Start Date</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->CONTR_START_DT?->format('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">End Date</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->CONTR_END_DT?->format('d M Y') ?? '-' }}</p>
            </div>
            
            @if($ticket->contract->CONTR_DIR_SHARE_LINK)
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Document Folder</p>
                <a href="{{ $ticket->contract->CONTR_DIR_SHARE_LINK }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                    <flux:icon.link class="size-4" />
                    Open Internal Sharing Folder
                    <flux:icon.arrow-top-right-on-square class="size-3" />
                </a>
            </div>
            @endif
            
            @if($ticket->contract->CONTR_TERMINATE_DT)
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Terminated At</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->CONTR_TERMINATE_DT->format('d M Y H:i') }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Termination Reason</p>
                <p class="font-medium text-red-600 dark:text-red-400">{{ $ticket->contract->CONTR_TERMINATE_REASON }}</p>
            </div>
            @endif
        </div>
    </div>
    @endif

<!-- Uploaded Documents (dynamic from file-type question answers) -->
    @php
        $fileAnswers = $ticket->answers->filter(fn ($a) => $a->question?->QUEST_TYPE === 'file' && $a->ANS_VALUE);
    @endphp
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Uploaded Documents</h2>

        @if($fileAnswers->count() > 0)
        <div class="space-y-4" x-data="{ previewImage: null }">
            @foreach($fileAnswers->sortBy(fn ($a) => $a->question?->QUEST_SORT_ORDER) as $answer)
                @php
                    $isMultiple = $answer->question?->QUEST_IS_MULTIPLE;
                    $label = $answer->question?->QUEST_LABEL ?? 'Document';
                @endphp

                <div>
                    <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ $label }}</p>

                    @if($isMultiple)
                        {{-- Multiple files stored as JSON array of paths --}}
                        @php
                            $rawFiles = json_decode($answer->ANS_VALUE, true) ?? [];
                            // Normalize: support both string paths and legacy {name, path} objects
                            $filePaths = collect($rawFiles)->map(fn ($f) => is_array($f) ? ($f['path'] ?? '') : $f)->filter()->values();
                        @endphp
                        @if($filePaths->count() > 0)
                        <div class="space-y-2">
                            @foreach($filePaths as $filePath)
                                @php
                                    $filename = basename($filePath);
                                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                    $isPdf = $ext === 'pdf';
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    $previewUrl = route('tickets.documents.preview', ['ticketNumber' => $ticket->TCKT_NO, 'path' => $filename]);
                                    $downloadUrl = route('tickets.documents.download', ['ticketNumber' => $ticket->TCKT_NO, 'path' => $filename]);
                                @endphp
                                <div class="flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                                    <div class="flex items-center gap-3 min-w-0">
                                        @if($isPdf)
                                            <flux:icon name="document-text" class="h-5 w-5 flex-shrink-0 text-red-500" />
                                        @elseif($isImage)
                                            <flux:icon name="photo" class="h-5 w-5 flex-shrink-0 text-green-500" />
                                        @else
                                            <flux:icon name="document" class="h-5 w-5 flex-shrink-0 text-neutral-500" />
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-neutral-900 dark:text-white">{{ $filename }}</p>
                                            <p class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ $ext }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        @if($isPdf)
                                            <a href="{{ $previewUrl }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                                                <flux:icon name="eye" class="h-3.5 w-3.5" /> Preview
                                            </a>
                                        @elseif($isImage)
                                            <button @click="previewImage = '{{ $previewUrl }}'" class="inline-flex items-center gap-1 rounded-lg bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50">
                                                <flux:icon name="eye" class="h-3.5 w-3.5" /> Preview
                                            </button>
                                        @endif
                                        <a href="{{ $downloadUrl }}" class="inline-flex items-center gap-1 rounded-lg bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600">
                                            <flux:icon name="arrow-down-tray" class="h-3.5 w-3.5" /> Download
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @endif
                    @else
                        {{-- Single file stored as path string --}}
                        @php
                            $filename = basename($answer->ANS_VALUE);
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $isPdf = $ext === 'pdf';
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            $previewUrl = route('tickets.documents.preview', ['ticketNumber' => $ticket->TCKT_NO, 'path' => $filename]);
                            $downloadUrl = route('tickets.documents.download', ['ticketNumber' => $ticket->TCKT_NO, 'path' => $filename]);
                        @endphp
                        <div class="flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
                            <div class="flex items-center gap-3 min-w-0">
                                @if($isPdf)
                                    <flux:icon name="document-text" class="h-5 w-5 flex-shrink-0 text-red-500" />
                                @elseif($isImage)
                                    <flux:icon name="photo" class="h-5 w-5 flex-shrink-0 text-green-500" />
                                @else
                                    <flux:icon name="document" class="h-5 w-5 flex-shrink-0 text-neutral-500" />
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-neutral-900 dark:text-white">{{ $filename }}</p>
                                    <p class="text-xs uppercase text-neutral-500 dark:text-neutral-400">{{ $ext }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($isPdf)
                                    <a href="{{ $previewUrl }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                                        <flux:icon name="eye" class="h-3.5 w-3.5" /> Preview
                                    </a>
                                @elseif($isImage)
                                    <button @click="previewImage = '{{ $previewUrl }}'" class="inline-flex items-center gap-1 rounded-lg bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50">
                                        <flux:icon name="eye" class="h-3.5 w-3.5" /> Preview
                                    </button>
                                @endif
                                <a href="{{ $downloadUrl }}" class="inline-flex items-center gap-1 rounded-lg bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-600">
                                    <flux:icon name="arrow-down-tray" class="h-3.5 w-3.5" /> Download
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Image Preview Modal (Alpine.js) --}}
            <div x-show="previewImage" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click.self="previewImage = null"
                 @keydown.escape.window="previewImage = null"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
                <div class="relative max-h-[90vh] max-w-4xl">
                    <button @click="previewImage = null"
                            class="absolute -right-3 -top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-neutral-700 shadow-lg hover:bg-neutral-100 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700">
                        <flux:icon name="x-mark" class="h-5 w-5" />
                    </button>
                    <img :src="previewImage" alt="Document Preview"
                         class="max-h-[85vh] rounded-lg object-contain shadow-2xl" />
                </div>
            </div>
        </div>
        @else
        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">No uploaded documents</p>
        @endif
    </div>

{{-- Dynamic Answer Sections (driven by FormSection table) --}}
        @php
            $detailSections = \App\Models\FormSection::active()->forDetail()->ordered()->get();
        @endphp
        @foreach($detailSections as $detailSection)
            @php
                $sectionAnswers = $ticket->answers->filter(fn ($a) =>
                    $a->question?->QUEST_SECTION === $detailSection->SECT_CODE
                    && $a->question?->QUEST_TYPE !== 'file'
                    && $a->ANS_VALUE !== null
                    && $a->ANS_VALUE !== ''
                );
            @endphp
            @if($sectionAnswers->count() > 0)
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">{{ $detailSection->SECT_LABEL }}</h2>
                @if($detailSection->SECT_DESCRIPTION)
                    <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">{{ $detailSection->SECT_DESCRIPTION }}</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($sectionAnswers->sortBy(fn ($a) => $a->question?->QUEST_SORT_ORDER) as $answer)
                        <div class="{{ $answer->question?->QUEST_WIDTH === 'full' ? 'sm:col-span-2' : '' }}">
                            <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $answer->question?->QUEST_LABEL }}</p>
                            <p class="font-medium text-neutral-900 dark:text-white">
                                @if($answer->question?->QUEST_TYPE === 'boolean')
                                    @if($answer->ANS_VALUE)
                                        <flux:badge color="green">Yes</flux:badge>
                                    @else
                                        <flux:badge color="neutral">No</flux:badge>
                                    @endif
                                @elseif($answer->question?->QUEST_TYPE === 'select')
                                    @php
                                        $option = collect($answer->question?->QUEST_OPTIONS ?? [])->firstWhere('value', $answer->ANS_VALUE);
                                    @endphp
                                    <flux:badge color="blue">{{ $option['label'] ?? $answer->ANS_VALUE }}</flux:badge>
                                @elseif($answer->question?->QUEST_TYPE === 'date')
                                    {{ \Carbon\Carbon::parse($answer->ANS_VALUE)->format('d M Y') }}
                                @else
                                    {{ $answer->ANS_VALUE }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach

{{-- Finalization Checklist Answers --}}
    @php
        $finAnswers = $ticket->answers->filter(fn ($a) => $a->question?->QUEST_SECTION === 'finalization');
    @endphp
    @if($ticket->status?->LOV_VALUE === 'done' && $finAnswers->count() > 0)
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Finalization Checklist</h2>
        
        <div class="space-y-3">
            @foreach($finAnswers->sortBy(fn ($a) => $a->question?->QUEST_SORT_ORDER) as $answer)
                @if($answer->question?->QUEST_TYPE === 'boolean')
                <div class="flex items-start gap-3">
                    @if($answer->ANS_VALUE)
                        <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                    @else
                        <flux:icon.x-circle class="size-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                    @endif
                    <div>
                        <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ $answer->question->QUEST_LABEL }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $answer->ANS_VALUE ? 'Yes' : 'No' }}</p>
                    </div>
                </div>
                @elseif($answer->ANS_VALUE)
                <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800">
                    <p class="mb-1 text-xs font-medium text-neutral-500 dark:text-neutral-400">{{ $answer->question->QUEST_LABEL }}:</p>
                    <p class="text-sm text-neutral-900 dark:text-white whitespace-pre-wrap">{{ $answer->ANS_VALUE }}</p>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

<!-- Activity Log -->
    @php
        $allLogs = $ticket->activityLogs;
        if ($ticket->contract && $ticket->contract->activityLogs) {
            $allLogs = $allLogs->merge($ticket->contract->activityLogs);
        }
        $allLogs = $allLogs->sortByDesc('created_at');
    @endphp
    
    @if($allLogs->count() > 0)
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Activity Log</h2>
        
        <div class="space-y-4">
            @foreach($allLogs as $log)
            <div class="flex gap-3 border-l-2 border-neutral-200 pl-4 dark:border-neutral-700">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ $log->action }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $log->user?->name ?? 'System' }} • {{ $log->created_at->format('d M Y H:i') }}
                    </p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

<!-- Reject Modal -->
    <flux:modal name="reject-modal" :open="$showRejectModal" wire:model="showRejectModal">
        <form wire:submit="rejectTicket" class="space-y-6">
            <div>
                <flux:heading size="lg">Reject Ticket</flux:heading>
                <flux:subheading>Please provide a reason for rejecting this ticket</flux:subheading>
            </div>
            <flux:field>
                <flux:label>Rejection Reason *</flux:label>
                <flux:textarea wire:model="rejectionReason" rows="4" placeholder="Explain rejection reason..." required />
                <flux:error name="rejectionReason" />
            </flux:field>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" wire:click="$set('showRejectModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="danger">Reject Ticket</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Terminate Contract Modal -->
    <flux:modal name="terminate-modal" :open="$showTerminateModal" wire:model="showTerminateModal">
        <form wire:submit="terminateContract" class="space-y-6">
            <div>
                <flux:heading size="lg">Terminate Contract</flux:heading>
                <flux:subheading>Please provide a reason for terminating this contract</flux:subheading>
            </div>
            <flux:field>
                <flux:label>Termination Reason *</flux:label>
                <flux:textarea wire:model="terminationReason" rows="4" placeholder="Explain termination reason..." required />
                <flux:error name="terminationReason" />
            </flux:field>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" wire:click="$set('showTerminateModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="danger">Terminate Contract</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Dynamic Pre-Done Questions Modal --}}
    @if($this->hasFinalizationQuestions)
    <flux:modal name="pre-done-modal" :open="$showPreDoneModal" wire:model="showPreDoneModal">
        <form wire:submit="moveToDone" class="space-y-6">
            <div>
                <flux:heading size="lg">Pre-Finalization Checklist</flux:heading>
                <flux:subheading>Please answer all questions before completing the ticket</flux:subheading>
            </div>

            <div class="space-y-4">
                @foreach($this->finalizationQuestions as $index => $question)
                <flux:field wire:key="modal-fin-{{ $question->QUEST_CODE }}">
                    <flux:label>{{ $index + 1 }}. {{ $question->QUEST_LABEL }} {{ $question->QUEST_IS_REQUIRED ? '*' : '' }}</flux:label>
                    
                    @if($question->QUEST_TYPE === 'boolean')
                        <div class="flex gap-4 mt-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" value="1" class="size-4 text-blue-600" {{ $question->QUEST_IS_REQUIRED ? 'required' : '' }} />
                                <span class="text-sm text-neutral-700 dark:text-neutral-300">Yes</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" value="0" class="size-4 text-blue-600" {{ $question->QUEST_IS_REQUIRED ? 'required' : '' }} />
                                <span class="text-sm text-neutral-700 dark:text-neutral-300">No</span>
                            </label>
                        </div>
                    @elseif($question->QUEST_TYPE === 'text')
                        <flux:textarea wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" rows="3" placeholder="{{ $question->QUEST_PLACEHOLDER }}" />
                    @else
                        <flux:input wire:model="finalizationAnswers.{{ $question->QUEST_CODE }}" placeholder="{{ $question->QUEST_PLACEHOLDER }}" />
                    @endif

                    @if($question->QUEST_DESCRIPTION)
                        <flux:description>{{ $question->QUEST_DESCRIPTION }}</flux:description>
                    @endif
                    <flux:error name="finalizationAnswers.{{ $question->QUEST_CODE }}" />
                </flux:field>
                @endforeach
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" wire:click="$set('showPreDoneModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Continue & Complete Ticket</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif

</div>

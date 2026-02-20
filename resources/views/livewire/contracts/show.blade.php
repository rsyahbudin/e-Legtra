<?php

use App\Models\Contract;
use App\Models\Ticket;
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

    public bool $preDoneQ1 = false;

    public bool $preDoneQ2 = false;

    public bool $preDoneQ3 = false;

    public string $preDoneRemarks = '';

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
        ])->findOrFail($contract);
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
        $this->preDoneQ1 = (bool) $this->ticket->TCKT_POST_QUEST_1;
        $this->preDoneQ2 = (bool) $this->ticket->TCKT_POST_QUEST_2;
        $this->preDoneQ3 = (bool) $this->ticket->TCKT_POST_QUEST_3;
        $this->preDoneRemarks = $this->ticket->TCKT_POST_RMK ?? '';

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

        $preDoneAnswers = null;
        $remarks = null;
        if ($this->ticket->documentType?->code === 'perjanjian') {
            $this->validate([
                'preDoneQ1' => 'required|boolean',
                'preDoneQ2' => 'required|boolean',
                'preDoneQ3' => 'required|boolean',
                'preDoneRemarks' => 'nullable|string|max:1000',
            ], [
                'preDoneQ1.required' => 'Question 1 must be answered',
                'preDoneQ2.required' => 'Question 2 must be answered',
                'preDoneQ3.required' => 'Question 3 must be answered',
                'preDoneRemarks.max' => 'Remarks maximum 1000 characters',
            ]);

            $preDoneAnswers = [$this->preDoneQ1, $this->preDoneQ2, $this->preDoneQ3];
            $remarks = $this->preDoneRemarks;
        }

        $ticketService = app(TicketService::class);
        $oldStatus = $this->ticket->status?->LOV_VALUE;
        $ticketService->moveToDone($this->ticket, $preDoneAnswers, $remarks);

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
            <!-- Edit Button (visible anytime for legal) -->
            <a href="{{ route('tickets.edit', $ticket->LGL_ROW_ID) }}" wire:navigate>
                <flux:button variant="ghost" icon="pencil">
                    Edit Ticket
                </flux:button>
            </a>

            @if($ticket->status?->LOV_VALUE === 'open')
                <flux:button wire:click="moveToOnProcess" variant="primary" icon="play">
                    Process Ticket
                </flux:button>
                <flux:button wire:click="openRejectModal" variant="danger" icon="x-mark">
                    Reject Ticket
                </flux:button>
            @elseif($ticket->status?->LOV_VALUE === 'on_process')
                @php
                    $isContractable = in_array($ticket->documentType?->code, ['perjanjian', 'nda', 'surat_kuasa']);
                @endphp
                
                @if($isContractable)
                    @if($ticket->documentType?->code === 'perjanjian')
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
                <flux:button wire:click="openTerminateModal" variant="danger" icon="x-circle">
                    Terminate Contract
                </flux:button>
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
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->documentType->REF_DOC_TYPE_NAME }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Document Title (Proposed)</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_PROP_DOC_TITLE }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Division</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->division->REF_DIV_NAME }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Department</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->department?->REF_DEPT_NAME ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Created By</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->creator->name }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Created Date</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_CREATED_DT->format('d M Y H:i') }}</p>
            </div>
            
            @if($ticket->TCKT_REVIEWED_BY)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Reviewed By</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->reviewer->name }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Review Date</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_REVIEWED_DT->format('d M Y H:i') }}</p>
            </div>
            @endif

            @if($ticket->TCKT_REJECT_REASON)
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Rejection Reason</p>
                <p class="font-medium text-red-600 dark:text-red-400">{{ $ticket->TCKT_REJECT_REASON }}</p>
            </div>
            @endif

            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Financial Impact</p>
                <p class="font-medium text-neutral-900 dark:text-white">
                    @if($ticket->TCKT_HAS_FIN_IMPACT)
                        <flux:badge color="green">Yes</flux:badge>
                    @else
                        <flux:badge color="neutral">No</flux:badge>
                    @endif
                </p>
            </div>

            @if($ticket->TCKT_HAS_FIN_IMPACT && $ticket->TCKT_PAYMENT_TYPE)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Payment Type</p>
                <p class="font-medium text-neutral-900 dark:text-white">
                    @if($ticket->TCKT_PAYMENT_TYPE === 'pay')
                        <flux:badge color="orange">Pay</flux:badge>
                    @elseif($ticket->TCKT_PAYMENT_TYPE === 'receive_payment')
                        <flux:badge color="blue">Receive</flux:badge>
                    @endif
                </p>
            </div>
            
            @if($ticket->TCKT_PAYMENT_TYPE === 'pay' && $ticket->TCKT_RECURRING_DESC)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Recurring Description</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_RECURRING_DESC }}</p>
            </div>
            @endif
            @endif

            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Legal Turn Around Time</p>
                <p class="font-medium text-neutral-900 dark:text-white">
                    @if($ticket->TCKT_TAT_LGL_COMPLNCE)
                        <flux:badge color="green">Yes</flux:badge>
                    @else
                        <flux:badge color="neutral">No</flux:badge>
                    @endif
                </p>
            </div>

        </div>

        <!-- Document-specific details -->
        @if(in_array($ticket->documentType?->code, ['perjanjian', 'nda']) && $ticket->TCKT_COUNTERPART_NAME)
        <div class="mt-6 border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <h3 class="mb-3 font-semibold text-neutral-900 dark:text-white">{{ $ticket->documentType?->code === 'nda' ? 'NDA' : 'Agreement' }} Details</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Counterpart</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_COUNTERPART_NAME }}</p>
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Start Date</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_AGREE_START_DT?->format('d M Y') ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Duration</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_AGREE_DURATION }}</p>
                </div>
                @if(!$ticket->TCKT_IS_AUTO_RENEW && $ticket->TCKT_AGREE_END_DT)
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">End Date</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_AGREE_END_DT->format('d M Y') }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif


    </div>

    {{-- Pre-Done Questions Answers (for Perjanjian only) --}}
    @if($ticket->documentType?->code === 'perjanjian' && $ticket->status?->LOV_VALUE === 'done' && ($ticket->TCKT_POST_QUEST_1 !== null || $ticket->TCKT_POST_QUEST_2 !== null || $ticket->TCKT_POST_QUEST_3 !== null))
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Finalization Checklist</h2>
        
        <div class="space-y-3">
            <div class="flex items-start gap-3">
                @if($ticket->TCKT_POST_QUEST_1)
                    <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                @else
                    <flux:icon.x-circle class="size-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                @endif
                <div>
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">
                        Has the document been signed by both parties?
                    </p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $ticket->TCKT_POST_QUEST_1 ? 'Yes' : 'No' }}
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                @if($ticket->TCKT_POST_QUEST_2)
                    <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                @else
                    <flux:icon.x-circle class="size-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                @endif
                <div>
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">
                        Has the final document been saved in the internal sharing folder?
                    </p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $ticket->TCKT_POST_QUEST_2 ? 'Yes' : 'No' }}
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                @if($ticket->TCKT_POST_QUEST_3)
                    <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0" />
                @else
                    <flux:icon.x-circle class="size-5 text-red-600 dark:text-red-400 flex-shrink-0" />
                @endif
                <div>
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">
                        Are all mandatory attachments complete?
                    </p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $ticket->TCKT_POST_QUEST_3 ? 'Yes' : 'No' }}
                    </p>
                </div>
            </div>

            @if($ticket->TCKT_POST_RMK)
            <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="mb-1 text-xs font-medium text-neutral-500 dark:text-neutral-400">Remarks:</p>
                <p class="text-sm text-neutral-900 dark:text-white whitespace-pre-wrap">{{ $ticket->TCKT_POST_RMK }}</p>
            </div>
            @endif
        </div>
    </div>
    @endif

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

            @if($ticket->documentType?->code === 'surat_kuasa' && $ticket->TCKT_GRANTOR)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Grantor (Pemberi Kuasa)</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_GRANTOR }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Grantee (Penerima Kuasa)</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_GRANTEE }}</p>
            </div>
            @endif
            
            @if($ticket->contract->CONTR_DIR_SHARE_LINK)
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Document Folder</p>
                <a 
                    href="{{ $ticket->contract->CONTR_DIR_SHARE_LINK }}" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium"
                >
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

    <!-- Uploaded Documents -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Uploaded Documents</h2>
        
        <div class="space-y-4">
            @if($ticket->TCKT_DOC_PATH)
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Draft Document</p>
                <a href="{{ Storage::url($ticket->TCKT_DOC_PATH) }}" download target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                    <flux:icon name="document-text" class="h-4 w-4" />
                    <span>Download Draft</span>
                    <flux:icon name="arrow-down-tray" class="h-4 w-4" />
                </a>
            </div>
            @endif

            @if($ticket->TCKT_DOC_REQUIRED_PATH && count($ticket->TCKT_DOC_REQUIRED_PATH) > 0)
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Mandatory Documents ({{ count($ticket->TCKT_DOC_REQUIRED_PATH) }} files)</p>
                <div class="space-y-2">
                    @foreach($ticket->TCKT_DOC_REQUIRED_PATH as $index => $doc)
                    <a href="{{ Storage::url($doc['path']) }}" download target="_blank" class="flex items-center gap-2 rounded-lg bg-purple-50 px-3 py-2 text-sm text-purple-700 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50">
                        <flux:icon name="document" class="h-4 w-4" />
                        <span class="flex-1">{{ $doc['name'] }}</span>
                        <flux:icon name="arrow-down-tray" class="h-4 w-4" />
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            @if($ticket->TCKT_DOC_APPROVAL_PATH)
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Approval Document</p>
                <a href="{{ Storage::url($ticket->TCKT_DOC_APPROVAL_PATH) }}" download target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-2 text-sm text-orange-700 hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-400 dark:hover:bg-orange-900/50">
                    <flux:icon name="document-check" class="h-4 w-4" />
                    <span>Download Approval</span>
                    <flux:icon name="arrow-down-tray" class="h-4 w-4" />
                </a>
            </div>
            @endif

            @if(!$ticket->TCKT_DOC_PATH && (!$ticket->TCKT_DOC_REQUIRED_PATH || count($ticket->TCKT_DOC_REQUIRED_PATH) == 0) && !$ticket->TCKT_DOC_APPROVAL_PATH)
            <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">No uploaded documents</p>
            @endif
        </div>
    </div>

    <!-- Activity Log -->
    @php
        // Merge ticket and contract activity logs
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

    {{-- Pre-Done Questions Modal --}}
    <flux:modal name="pre-done-modal" :open="$showPreDoneModal" wire:model="showPreDoneModal">
        <form wire:submit="moveToDone" class="space-y-6">
            <div>
                <flux:heading size="lg">Pre-Finalization Checklist</flux:heading>
                <flux:subheading>Please answer all questions before completing the ticket</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>1. Has the document been signed by both parties? *</flux:label>
                    <div class="flex gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preDoneQ1" value="1" class="size-4 text-blue-600" required />
                            <span class="text-sm text-neutral-700 dark:text-neutral-300">Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preDoneQ1" value="0" class="size-4 text-blue-600" required />
                            <span class="text-sm text-neutral-700 dark:text-neutral-300">No</span>
                        </label>
                    </div>
                    <flux:error name="preDoneQ1" />
                </flux:field>

                <flux:field>
                    <flux:label>2. Has the final document been saved in the internal sharing folder? *</flux:label>
                    <div class="flex gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preDoneQ2" value="1" class="size-4 text-blue-600" required />
                            <span class="text-sm text-neutral-700 dark:text-neutral-300">Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preDoneQ2" value="0" class="size-4 text-blue-600" required />
                            <span class="text-sm text-neutral-700 dark:text-neutral-300">No</span>
                        </label>
                    </div>
                    <flux:error name="preDoneQ2" />
                </flux:field>

                <flux:field>
                    <flux:label>3. Are all mandatory attachments complete? *</flux:label>
                    <div class="flex gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preDoneQ3" value="1" class="size-4 text-blue-600" required />
                            <span class="text-sm text-neutral-700 dark:text-neutral-300">Yes</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="preDoneQ3" value="0" class="size-4 text-blue-600" required />
                            <span class="text-sm text-neutral-700 dark:text-neutral-300">No</span>
                        </label>
                    </div>
                    <flux:error name="preDoneQ3" />
                </flux:field>

                <flux:field>
                    <flux:label>Remarks (Optional)</flux:label>
                    <flux:textarea wire:model="preDoneRemarks" rows="3" placeholder="Additional notes or remarks (max 1000 characters)" />
                    <flux:description>Maximum 1000 characters</flux:description>
                    <flux:error name="preDoneRemarks" />
                </flux:field>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" wire:click="$set('showPreDoneModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Continue & Complete Ticket</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

<?php

use App\Models\Division;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;


new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $divisionFilter = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $typeFilter = '';

    public int $perPage = 10;

    public string $sortCol = 'TCKT_CREATED_DT';
    public bool $sortAsc = false;

    // Folder Link Modal
    public bool $showFolderLinkModal = false;

    public $selectedContract = null;

    public string $folder_link = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDivisionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sortCol === $column) {
            $this->sortAsc = !$this->sortAsc;
        } else {
            $this->sortCol = $column;
            $this->sortAsc = true;
        }
        $this->resetPage();
    }

    public function getTicketsProperty(): LengthAwarePaginator
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $query = Ticket::with(['division', 'department', 'creator', 'contract', 'status', 'documentType'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('TCKT_NO', 'like', "%{$this->search}%")
                    ->orWhereHas('answers', function ($qA) {
                        $qA->whereHas('question', function ($qQ) {
                            $qQ->where('QUEST_CODE', 'proposed_document_title');
                        })->where('ANS_VALUE', 'like', "%{$this->search}%");
                    });
            }))
            ->when($this->statusFilter, fn ($q) => $q->whereHas('status', fn ($sq) => $sq->where('LOV_VALUE', $this->statusFilter)))
            ->when($this->divisionFilter, fn ($q) => $q->where('DIV_ID', $this->divisionFilter))
            ->when($this->typeFilter, fn ($q) => $q->whereHas('documentType', fn ($sq) => $sq->where('code', $this->typeFilter)))
            ->when($this->startDate, fn ($q) => $q->whereDate('TCKT_CREATED_DT', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('TCKT_CREATED_DT', '<=', $this->endDate));

        // Role-based filtering
        if ($user && ! $user->hasAnyRole(['super-admin', 'legal'])) {
            // Regular users: only see tickets from their department
            $query->where('DEPT_ID', $user->DEPT_ID);
        }
        // Legal & super admin: see all tickets

        return $query->orderBy($this->sortCol, $this->sortAsc ? 'asc' : 'desc')->paginate($this->perPage);
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('REF_DIV_NAME')->get();
    }

    // public function getTicketStatusesProperty()
    // {
    //     return \App\Models\TicketStatus::active()->orderBy('LOV_SEQ_NO')->get();
    // }

    public function getTicketStatusesProperty()
    {
        return TicketStatus::where('LOV_TYPE', 'TICKET_STATUS')
            ->where('IS_ACTIVE', 1)
            ->orderByRaw('COALESCE(LOV_SEQ_NO, 999)')
            ->orderBy('LOV_DISPLAY_NAME')
            ->get();
    }

    public function getDocumentTypesProperty()
    {
        return \App\Models\DocumentType::active()->get();
    }


    public function editFolderLink($contractId): void
    {
        $contract = \App\Models\Contract::findOrFail($contractId);
        $this->selectedContract = $contract;
        $this->folder_link = $contract->CONTR_DIR_SHARE_LINK ?? '';
        $this->showFolderLinkModal = true;
    }
    

    public function saveFolderLink(): void
    {
        $validated = $this->validate([
            'folder_link' => ['nullable', 'url', 'max:500'],
        ]);

        $oldLink = $this->selectedContract->CONTR_DIR_SHARE_LINK;

        $this->selectedContract->update([
            'CONTR_DIR_SHARE_LINK' => $this->folder_link ?: null,
        ]);

        // Log activity to the ticket
        if ($this->selectedContract->ticket) {
            $message = $oldLink
                ? 'Folder link updated'
                : 'Folder link added';
            $this->selectedContract->ticket->logActivity($message);
        }

        $this->showFolderLinkModal = false;
        $this->dispatch('notify', type: 'success', message: 'Folder link saved successfully');
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Ticket List</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                @if(auth()->user()->hasAnyRole(['super-admin', 'legal']))
                    Manage all tickets from all departments
                @else
                    Tickets you have created
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()?->hasPermission('reports.export'))
            <a href="{{ route('tickets.export', [
                'status' => $statusFilter,
                'type' => $typeFilter,
                'division' => $divisionFilter,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]) }}" class="inline-flex">
                <flux:button variant="ghost" icon="arrow-down-tray">
                    Export to Excel
                </flux:button>
            </a>
            @endif
            @if(auth()->user()?->hasPermission('tickets.create'))
            <a href="{{ route('tickets.create') }}" wire:navigate>
                <flux:button variant="primary" icon="plus">
                    Create New Ticket
                </flux:button>
            </a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search ticket number..." 
                icon="magnifying-glass"
            />
            
            <flux:input 
                type="date" 
                wire:model.live="startDate" 
                placeholder="Start Date"
                icon="calendar-days"
            />
            
            <flux:input 
                type="date" 
                wire:model.live="endDate" 
                placeholder="End Date"
                icon="calendar-days"
            />
            
            <flux:select wire:model.live="statusFilter">
                <option value="">All Ticket Statuses</option>
                @foreach($this->ticketStatuses as $status)
                    <option value="{{ $status->LOV_VALUE }}">{{ $status->LOV_DISPLAY_NAME }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="typeFilter" placeholder="Document Type">
                <flux:select.option value="">All Types</flux:select.option>
                @foreach($this->documentTypes as $type)
                    <flux:select.option value="{{ $type->code }}">{{ $type->REF_DOC_TYPE_NAME }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="divisionFilter">
                <option value="">All Divisions</option>
                @foreach($this->divisions as $division)
                <option value="{{ $division->LGL_ROW_ID }}">{{ $division->REF_DIV_NAME }}</option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left cursor-pointer hover:text-neutral-900 dark:hover:text-white" wire:click="sortBy('TCKT_NO')">
                            Ticket No.
                            @if($sortCol === 'TCKT_NO')
                                <flux:icon name="{{ $sortAsc ? 'chevron-up' : 'chevron-down' }}" class="inline w-3 h-3 ml-1" />
                            @endif
                        </th>
                        <th class="px-4 py-3 text-left">
                            Document Title
                        </th>
                        <!-- <th class="px-4 py-3 text-left">Divisi</th> -->
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center cursor-pointer hover:text-neutral-900 dark:hover:text-white" wire:click="sortBy('TCKT_CREATED_DT')">
                            Created
                            @if($sortCol === 'TCKT_CREATED_DT')
                                <flux:icon name="{{ $sortAsc ? 'chevron-up' : 'chevron-down' }}" class="inline w-3 h-3 ml-1" />
                            @endif
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer hover:text-neutral-900 dark:hover:text-white" wire:click="sortBy('TCKT_UPDATED_DT')">
                            Updated
                            @if($sortCol === 'TCKT_UPDATED_DT')
                                <flux:icon name="{{ $sortAsc ? 'chevron-up' : 'chevron-down' }}" class="inline w-3 h-3 ml-1" />
                            @endif
                        </th>
                        <th class="px-4 py-3 text-center">Aging</th>
                        <th class="px-4 py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->tickets as $ticket)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="row-{{ $ticket->LGL_ROW_ID }}">
                        <td class="px-4 py-3">
                            <span class="font-medium text-neutral-900 dark:text-white">{{ $ticket->TCKT_NO }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs">
                                <p class="truncate font-medium text-neutral-900 dark:text-white">{{ $ticket->getAnswer('proposed_document_title') ?? '-' }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $ticket->documentType->REF_DOC_TYPE_NAME }}</p>
                            </div>
                        </td>
                        <!-- <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->division->name }}
                        </td> -->
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->department?->REF_DEPT_NAME ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            
                            <flux:badge :color="$ticket->status?->color ?? 'neutral'" size="sm" inset="top bottom">{{ $ticket->status?->name ?? 'Unknown' }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->TCKT_CREATED_DT->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->TCKT_UPDATED_DT->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $ticket->aging_display === '-' ? 'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' }}">
                                {{ $ticket->aging_display }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('tickets.show', $ticket->LGL_ROW_ID) }}" wire:navigate>
                                    <flux:button size="sm" variant="ghost" icon="eye" />
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <flux:icon name="ticket" class="h-12 w-12 text-neutral-300 dark:text-neutral-600" />
                                <p class="mt-4 text-neutral-500 dark:text-neutral-400">No tickets yet</p>
                                @if(auth()->user()?->hasPermission('tickets.create'))
                                <a href="{{ route('tickets.create') }}" class="mt-2" wire:navigate>
                                    <flux:button variant="primary" size="sm">Create First Ticket</flux:button>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->tickets->hasPages())
        <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
            {{ $this->tickets->links() }}
        </div>
        @endif

    </div>
</div>

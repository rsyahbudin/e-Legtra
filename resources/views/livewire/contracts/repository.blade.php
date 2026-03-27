<?php

use App\Models\Contract;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $divisionFilter = '';

    public int $perPage = 10;

    public string $sortCol = 'CONTR_CREATED_DT';
    public bool $sortAsc = false;

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

    public function getContractsProperty(): LengthAwarePaginator
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $query = Contract::with(['department', 'division'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('CONTR_NO', 'ilike', "%{$this->search}%")
                    ->orWhere('CONTR_AGREE_NAME', 'ilike', "%{$this->search}%");
            }))
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'active') {
                    $q->active();
                } elseif ($this->statusFilter === 'expired') {
                    $q->expired();
                } else {
                    $q->whereHas('status', fn ($sq) => $sq->where('LOV_VALUE', $this->statusFilter));
                }
            })
            ->when($this->typeFilter, fn ($q) => $q->where('CONTR_DOC_TYPE_ID', $this->typeFilter))
            ->when($this->divisionFilter, fn ($q) => $q->where('CONTR_DIV_ID', $this->divisionFilter));

        // Role-based filtering (Users see only their department contracts, Legal/Admin sees all)
        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $query->where('CONTR_DEPT_ID', $user->DEPT_ID);
        }

        return $query->orderBy($this->sortCol, $this->sortAsc ? 'asc' : 'desc')->paginate($this->perPage);
    }

    public function getDivisionsProperty()
    {
        return \App\Models\Division::active()->orderBy('REF_DIV_NAME')->get();
    }

    public function getContractStatusesProperty()
    {
        return \App\Models\ContractStatus::active()->orderBy('LOV_SEQ_NO')->get();
    }

    public function getDocumentTypesProperty()
    {
        return \App\Models\DocumentType::active()
            ->where('requires_contract', 1)
            ->orderBy('LGL_ROW_ID')
            ->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Contract Repository</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                List of all contracts created from tickets.
            </p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()?->hasPermission('reports.export'))
            <a href="{{ route('contracts.export', [
                'status' => $statusFilter,
                'type' => $typeFilter,
                'division' => $divisionFilter,
            ]) }}" class="inline-flex">
                <flux:button variant="ghost" icon="arrow-down-tray">
                    Export to Excel
                </flux:button>
            </a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center bg-white p-4 rounded-xl border border-neutral-200 dark:bg-zinc-900 dark:border-neutral-700 shadow-sm">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search Contracts..." />
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="typeFilter" placeholder="Document Type">
                <flux:select.option value="">All Types</flux:select.option>
                @foreach($this->documentTypes as $type)
                    <flux:select.option value="{{ $type->LGL_ROW_ID }}">{{ $type->REF_DOC_TYPE_NAME }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="statusFilter" placeholder="Filter Status">
                <flux:select.option value="">All Statuses</flux:select.option>
                @foreach($this->contractStatuses as $status)
                    <flux:select.option value="{{ $status->LOV_VALUE }}">{{ $status->LOV_DISPLAY_NAME }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if(auth()->user()->hasAnyRole(['super-admin', 'legal']))
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="divisionFilter" placeholder="Filter Division">
                <flux:select.option value="">All Divisions</flux:select.option>
                @foreach($this->divisions as $division)
                    <flux:select.option value="{{ $division->LGL_ROW_ID }}">{{ $division->REF_DIV_NAME }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @endif
        <div class="w-full sm:w-32">
            <flux:select wire:model.live="perPage">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </flux:select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-6 py-3 font-medium cursor-pointer hover:text-neutral-900 dark:hover:text-white" wire:click="sortBy('CONTR_NO')">
                            Contract #
                            @if($sortCol === 'CONTR_NO')
                                <flux:icon name="{{ $sortAsc ? 'chevron-up' : 'chevron-down' }}" class="inline w-3 h-3 ml-1" />
                            @endif
                        </th>
                        <th class="px-6 py-3 font-medium cursor-pointer hover:text-neutral-900 dark:hover:text-white" wire:click="sortBy('CONTR_AGREE_NAME')">
                            Title
                            @if($sortCol === 'CONTR_AGREE_NAME')
                                <flux:icon name="{{ $sortAsc ? 'chevron-up' : 'chevron-down' }}" class="inline w-3 h-3 ml-1" />
                            @endif
                        </th>
                        <th class="px-6 py-3 font-medium">Department</th>
                        <th class="px-6 py-3 font-medium cursor-pointer hover:text-neutral-900 dark:hover:text-white" wire:click="sortBy('CONTR_START_DT')">
                            Start Date
                            @if($sortCol === 'CONTR_START_DT')
                                <flux:icon name="{{ $sortAsc ? 'chevron-up' : 'chevron-down' }}" class="inline w-3 h-3 ml-1" />
                            @endif
                        </th>
                        <th class="px-6 py-3 font-medium cursor-pointer hover:text-neutral-900 dark:hover:text-white" wire:click="sortBy('CONTR_END_DT')">
                            End Date
                            @if($sortCol === 'CONTR_END_DT')
                                <flux:icon name="{{ $sortAsc ? 'chevron-up' : 'chevron-down' }}" class="inline w-3 h-3 ml-1" />
                            @endif
                        </th>
                        <th class="px-6 py-3 font-medium text-center">Status</th>
                        <th class="px-6 py-3 font-medium text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->contracts as $contract)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800">
                        <td class="px-6 py-4 font-medium text-neutral-900 dark:text-white whitespace-nowrap">
                            {{ $contract->CONTR_NO }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-neutral-900 dark:text-white">{{ $contract->CONTR_AGREE_NAME }}</div>
                            <div class="text-xs text-neutral-500">{{ $contract->document_type_label }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $contract->department->REF_DEPT_NAME ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $contract->CONTR_START_DT?->format('d M Y') ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($contract->CONTR_IS_AUTO_RENEW)
                                <div class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
                                    <flux:icon name="arrow-path" class="h-4 w-4" />
                                    <span class="text-xs font-medium">Auto Renewal</span>
                                </div>
                                @if($contract->CONTR_END_DT)
                                    <span class="text-xs text-neutral-400 block mt-1">Next: {{ $contract->CONTR_END_DT->format('d M Y') }}</span>
                                @endif
                            @else
                                {{ $contract->CONTR_END_DT?->format('d M Y') ?? '-' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $color = $contract->status?->color ?? 'neutral';
                            @endphp
                            <flux:badge :color="$color" size="sm" inset="top bottom">{{ $contract->status?->name ?? 'Unknown' }}</flux:badge>
                        </td>
                        <td class="px-6 py-4 text-end">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button :href="route('tickets.show', $contract->TCKT_ID)" icon="eye" size="sm" variant="ghost" class="-my-1" />
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-neutral-500">
                            No contracts found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Showing <span class="font-medium">{{ $this->contracts->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $this->contracts->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $this->contracts->total() }}</span> results
                </p>
                <div class="w-full sm:w-auto">
                     {{ $this->contracts->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

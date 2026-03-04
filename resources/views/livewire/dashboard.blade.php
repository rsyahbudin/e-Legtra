<?php

use App\Models\Contract;
use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public function isLegalUser(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user && $user->hasAnyRole(['super-admin', 'legal']);
    }

    // TICKET STATISTICS (for Legal Dashboard)
    public function getTicketStatisticsProperty(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('dashboard.tickets.view')) {
            return [];
        }

        return [
            'total' => Ticket::count(),
            'open' => Ticket::whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'open'))->count(),
            'on_process' => Ticket::whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'on_process'))->count(),
            'done' => Ticket::whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'done'))->count(),
            'rejected' => Ticket::whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'rejected'))->count(),
            'closed' => Ticket::whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'closed'))->count(),
        ];
    }

    public function getAgingOverviewProperty(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('dashboard.aging.view')) {
            return [];
        }

        $onProcessTickets = Ticket::where('TCKT_STS_ID', \App\Models\TicketStatus::getIdByCode('on_process'))
            ->whereNotNull('TCKT_AGING_START_DT')
            ->get();

        $doneTickets = Ticket::where('TCKT_STS_ID', \App\Models\TicketStatus::getIdByCode('done'))
            ->whereNotNull('TCKT_AGING_DURATION')
            ->get();

        $avgOngoing = $onProcessTickets->avg(function ($ticket) {
            return $ticket->TCKT_AGING_START_DT
                ? now()->diffInMinutes($ticket->TCKT_AGING_START_DT)
                : 0;
        });

        $avgCompleted = $doneTickets->avg('TCKT_AGING_DURATION') ?? 0;

        $longest = $onProcessTickets->sortByDesc(function ($ticket) {
            return $ticket->TCKT_AGING_START_DT
                ? now()->diffInMinutes($ticket->TCKT_AGING_START_DT)
                : 0;
        })->first();

        return [
            'avg_ongoing_minutes' => round($avgOngoing),
            'avg_completed_minutes' => round($avgCompleted),
            'longest_ticket' => $longest,
        ];
    }

    public function getTicketsNeedingAttentionProperty(): Collection
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('dashboard.tickets.view')) {
            return collect();
        }

        return Ticket::with(['creator', 'division', 'contract'])
            ->whereHas('status', fn ($q) => $q->whereIn('LOV_VALUE', ['open', 'on_process']))
            ->orderBy('TCKT_CREATED_DT', 'asc')
            ->limit(5)
            ->get();
    }

    // USER TICKET STATISTICS (for Regular User Dashboard)
    public function getMyTicketStatisticsProperty(): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('dashboard.my-tickets.view')) {
            return [];
        }

        return [
            'total' => Ticket::where('DEPT_ID', $user->DEPT_ID)->count(),
            'open' => Ticket::where('DEPT_ID', $user->DEPT_ID)->where('TCKT_STS_ID', \App\Models\TicketStatus::getIdByCode('open'))->count(),
            'on_process' => Ticket::where('DEPT_ID', $user->DEPT_ID)->where('TCKT_STS_ID', \App\Models\TicketStatus::getIdByCode('on_process'))->count(),
            'done' => Ticket::where('DEPT_ID', $user->DEPT_ID)->where('TCKT_STS_ID', \App\Models\TicketStatus::getIdByCode('done'))->count(),
            'rejected' => Ticket::where('DEPT_ID', $user->DEPT_ID)->where('TCKT_STS_ID', \App\Models\TicketStatus::getIdByCode('rejected'))->count(),
            'closed' => Ticket::where('DEPT_ID', $user->DEPT_ID)->where('TCKT_STS_ID', \App\Models\TicketStatus::getIdByCode('closed'))->count(),
        ];
    }

    public function getMyRecentTicketsProperty(): Collection
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission('dashboard.my-tickets.view')) {
            return collect();
        }

        return Ticket::with(['division', 'department', 'contract'])
            ->where('DEPT_ID', $user->DEPT_ID)
            ->orderBy('TCKT_CREATED_DT', 'desc')
            ->limit(5)
            ->get();
    }

    // CONTRACT STATISTICS (existing, for Legal Dashboard)
    public function getStatisticsProperty(): array
    {
        $user = auth()->user();
        if (! $user) {
            return ['total' => 0, 'active' => 0, 'expired' => 0, 'terminated' => 0];
        }

        $query = Contract::query();

        // Role-based filtering: regular users see contracts from their department
        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $query->where('CONTR_DEPT_ID', $user->DEPT_ID);
        }

        $total = (clone $query)->count();
        $active = (clone $query)->active()->count();
        $expired = (clone $query)->expired()->count();
        $terminated = (clone $query)->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'terminated'))->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'terminated' => $terminated,
        ];
    }

    public function getRecentContractsProperty(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        $query = Contract::with(['division', 'pic', 'ticket'])
            ->orderBy('CONTR_CREATED_DT', 'desc');

        // Role-based filtering: regular users see contracts from their department
        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $query->where('CONTR_DEPT_ID', $user->DEPT_ID);
        }

        return $query->limit(7)->get();
    }

    public function getExpiringContractsProperty(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);

        $query = Contract::with(['division', 'pic', 'ticket'])
            ->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'active'))
            ->whereDate('CONTR_END_DT', '<=', now()->addDays($warningThreshold))
            ->orderBy('CONTR_END_DT', 'asc');

        // Role-based filtering: regular users see contracts from their department
        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $query->where('CONTR_DEPT_ID', $user->DEPT_ID);
        }

        return $query->limit(5)->get();
    }
}; ?>

<div class="space-y-6">
    @if(auth()->user()->hasPermission('dashboard.tickets.view'))
    {{-- LEGAL DASHBOARD --}}
    
    <!-- Ticket Statistics Cards -->
    <div>
        <h2 class="mb-4 text-xl font-bold text-neutral-900 dark:text-white">Ticket Overview</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Total</p>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $this->ticketStatistics['total'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <p class="text-xs text-blue-700 dark:text-blue-400">Open</p>
                <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ $this->ticketStatistics['open'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950/30">
                <p class="text-xs text-yellow-700 dark:text-yellow-400">On Process</p>
                <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ $this->ticketStatistics['on_process'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/30">
                <p class="text-xs text-green-700 dark:text-green-400">Done</p>
                <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $this->ticketStatistics['done'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                <p class="text-xs text-red-700 dark:text-red-400">Rejected</p>
                <p class="text-2xl font-bold text-red-700 dark:text-red-400">{{ $this->ticketStatistics['rejected'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-xs text-neutral-600 dark:text-neutral-400">Closed</p>
                <p class="text-2xl font-bold text-neutral-600 dark:text-neutral-400">{{ $this->ticketStatistics['closed'] ?? 0 }}</p>
            </div>
        </div>
    </div>



    <!-- Tickets Needing Attention -->
    @if($this->ticketsNeedingAttention->isNotEmpty())
    <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="border-b border-neutral-200 p-4 dark:border-neutral-700">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-white">Tickets Needing Attention</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Ticket #</th>
                        <th class="px-4 py-3 text-left">Title</th>
                        <th class="px-4 py-3 text-left">Document Type</th>
                        <th class="px-4 py-3 text-left">Created By</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aging</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach($this->ticketsNeedingAttention as $ticket)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800">
                        <td class="px-4 py-3 text-sm font-medium">
                            <a href="{{ route('tickets.show', $ticket->LGL_ROW_ID) }}" class="text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                                {{ $ticket->TCKT_NO }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ Str::limit($ticket->getAnswer('proposed_document_title'), 40) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->document_type_label }}
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->creator->USER_FULLNAME ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusConfig = [
                                    'open' => ['class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', 'label' => 'Open'],
                                    'on_process' => ['class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', 'label' => 'On Process'],
                                ];
                                $config = $statusConfig[$ticket->status?->LOV_VALUE] ?? ['class' => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300', 'label' => ucfirst($ticket->status?->LOV_VALUE)];
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $config['class'] }}">
                                {{ $config['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $ticket->aging_display === '-' ? 'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' }}">
                                {{ $ticket->aging_display }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                    @if($this->ticketsNeedingAttention->isEmpty())
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-neutral-500 dark:text-neutral-400">
                            No tickets match the filter.
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <h2 class="text-xl font-bold text-neutral-900 dark:text-white">Contract Overview</h2>

    @else
    {{-- USER DASHBOARD --}}
    <div>
        <h2 class="mb-4 text-xl font-bold text-neutral-900 dark:text-white">My Tickets</h2>
        
        <!-- My Ticket Statistics -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Total</p>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $this->myTicketStatistics['total'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <p class="text-xs text-blue-700 dark:text-blue-400">Open</p>
                <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ $this->myTicketStatistics['open'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950/30">
                <p class="text-xs text-yellow-700 dark:text-yellow-400">On Process</p>
                <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ $this->myTicketStatistics['on_process'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/30">
                <p class="text-xs text-green-700 dark:text-green-400">Done</p>
                <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $this->myTicketStatistics['done'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                <p class="text-xs text-red-700 dark:text-red-400">Rejected</p>
                <p class="text-2xl font-bold text-red-700 dark:text-red-400">{{ $this->myTicketStatistics['rejected'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-xs text-neutral-600 dark:text-neutral-400">Closed</p>
                <p class="text-2xl font-bold text-neutral-600 dark:text-neutral-400">{{ $this->myTicketStatistics['closed'] ?? 0 }}</p>
            </div>
        </div>

        <!-- My Recent Tickets -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <h3 class="text-lg font-semibold text-neutral-900 dark:text-white">My Recent Tickets</h3>
                <a href="{{ route('tickets.index') }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                    View All →
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Ticket #</th>
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-left">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse($this->myRecentTickets as $ticket)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800">
                            <td class="px-4 py-3 text-sm font-medium">
                                <a href="{{ route('tickets.show', $ticket->LGL_ROW_ID) }}" class="text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                                    {{ $ticket->TCKT_NO }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ Str::limit($ticket->getAnswer('proposed_document_title'), 50) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusConfig = [
                                        'open' => ['class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', 'label' => 'Open'],
                                        'on_process' => ['class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', 'label' => 'On Process'],
                                        'done' => ['class' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', 'label' => 'Done'],
                                        'rejected' => ['class' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', 'label' => 'Rejected'],
                                        'closed' => ['class' => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300', 'label' => 'Closed'],
                                    ];
                                    $config = $statusConfig[$ticket->status?->LOV_VALUE] ?? ['class' => 'bg-neutral-100 text-neutral-800', 'label' => ucfirst($ticket->status?->LOV_VALUE)];
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $config['class'] }}">
                                    {{ $config['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $ticket->TCKT_CREATED_DT->format('d M Y') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-neutral-500 dark:text-neutral-400">
                                No invoices found. <a href="{{ route('tickets.create') }}" class="text-blue-600 hover:underline dark:text-blue-400" wire:navigate>Create first ticket</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <h2 class="mt-6 text-xl font-bold text-neutral-900 dark:text-white">Contract Overview</h2>
    @endif

    <!-- Contract Statistics Cards (shown for both legal and regular users) -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Contracts -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="document-text" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Total Contracts</p>
                    <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $this->statistics['total'] }}</p>
                </div>
            </div>
        </div>

        <!-- Active Contracts -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                    <flux:icon name="check-circle" class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Active</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->statistics['active'] }}</p>
                </div>
            </div>
        </div>

        <!-- Expired -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="x-circle" class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Expired</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->statistics['expired'] }}</p>
                </div>
            </div>
        </div>

        <!-- Terminated -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800">
                    <flux:icon name="no-symbol" class="h-6 w-6 text-neutral-600 dark:text-neutral-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Terminated</p>
                    <p class="text-2xl font-bold text-neutral-600 dark:text-neutral-400">{{ $this->statistics['terminated'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Expiring Soon Alert -->
    @if($this->expiringContracts->isNotEmpty())
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900/50 dark:bg-yellow-900/20">
        <div class="flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 text-yellow-600 dark:text-yellow-400" />
            <div class="flex-1">
                <h3 class="font-semibold text-yellow-800 dark:text-yellow-200">Attention: Contracts Expiring Soon</h3>
                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                    There are {{ $this->expiringContracts->count() }} contracts that need attention soon.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Recent Contracts Table -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Recent Contracts</h2>
                @if(auth()->user()?->hasPermission('contracts.view'))
                <a href="{{ route('contracts.repository') }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                    View All →
                </a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Contract No.</th>
                            <th class="px-4 py-3 text-left">Contract Title</th>
                            <th class="px-4 py-3 text-left">End Date</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse($this->recentContracts as $contract)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="contract-{{ $contract->LGL_ROW_ID }}">
                            <td class="px-4 py-3 text-sm font-medium text-neutral-900 dark:text-white">
                                {{ $contract->CONTR_NO }}
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->CONTR_AGREE_NAME ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->CONTR_END_DT?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $color = $contract->status_color;
                                    $badgeClass = match($color) {
                                        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                        default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $color === 'green' ? 'bg-green-500' : ($color === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                                    {{ $contract->status?->LOV_VALUE === 'terminated' ? 'Terminated' : ($contract->days_remaining > 0 ? $contract->days_remaining . ' days' : 'Expired') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-neutral-500 dark:text-neutral-400">
                                No contracts found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Contracts Needing Attention -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Needs Attention</h2>
            </div>
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse($this->expiringContracts as $contract)
                <div class="flex items-center gap-4 p-4" wire:key="expiring-{{ $contract->LGL_ROW_ID }}">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full {{ $contract->status_color === 'red' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-yellow-100 dark:bg-yellow-900/30' }}">
                        <flux:icon name="exclamation-triangle" class="h-5 w-5 {{ $contract->status_color === 'red' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="truncate font-medium text-neutral-900 dark:text-white">
                            {{ $contract->CONTR_NO }}
                        </p>
                        <p class="truncate text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $contract->CONTR_AGREE_NAME ?? '-' }} • {{ $contract->division->REF_DIV_NAME ?? '-' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-medium {{ $contract->status_color === 'red' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                            {{ $contract->status?->LOV_VALUE === 'terminated' ? 'Terminated' : ($contract->days_remaining > 0 ? $contract->days_remaining . ' days' : 'Expired') }}
                        </p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $contract->CONTR_END_DT?->format('d M Y') ?? '-' }}
                        </p>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center p-8 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <flux:icon name="check-circle" class="h-8 w-8 text-green-600 dark:text-green-400" />
                    </div>
                    <p class="mt-4 font-medium text-neutral-900 dark:text-white">All Good!</p>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        No contracts need immediate attention.
                    </p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

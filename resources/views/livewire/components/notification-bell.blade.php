<?php

use App\Models\Notification;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showDropdown = false;

    public function getNotificationsProperty()
    {
        return Notification::forUser(auth()->id())
            ->latest('REF_NOTIF_CREATED_DT')
            ->limit(10)
            ->get();
    }

    public function getUnreadCountProperty(): int
    {
        return Notification::forUser(auth()->id())
            ->unread()
            ->count();
    }

    public function markAsRead(int $id): void
    {
        $notification = Notification::findOrFail($id);
        if ((int) $notification->USER_ID === (int) auth()->id()) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        Notification::forUser(auth()->id())
            ->unread()
            ->update(['READ_AT' => now()]);
    }

    #[On('notification-created')]
    public function refreshNotifications(): void
    {
        // This will automatically refresh the component
    }
}; ?>

<div class="relative" x-data="{ open: false }">
    <button 
        @click="open = !open"
        class="relative rounded-lg p-2 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 dark:text-neutral-400 dark:hover:bg-zinc-800 dark:hover:text-neutral-200"
    >
        <flux:icon name="bell" class="h-5 w-5" />
        @if($this->unreadCount > 0)
        <span class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
            {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
        </span>
        @endif
    </button>

    <div 
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 top-full z-[1000] mt-2 w-80 rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-zinc-900"
    >
        <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
            <h3 class="font-semibold text-neutral-900 dark:text-white">Notifications</h3>
            @if($this->unreadCount > 0)
            <button wire:click="markAllAsRead" class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                Mark all as read
            </button>
            @endif
        </div>

        <div class="max-h-80 divide-y divide-neutral-100 overflow-y-auto dark:divide-neutral-800">
            @forelse($this->notifications as $notification)
            @php
                $icon = 'bell';
                $colorClass = 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400';
                $iconColor = 'text-neutral-600 dark:text-neutral-400';
                $url = '#';

                // Determine Icon & Color
                if (in_array($notification->NOTIFICATION_TYPE, ['contract_expiring', 'warning'])) {
                    $icon = 'clock';
                    $colorClass = 'bg-yellow-100 dark:bg-yellow-900/30';
                    $iconColor = 'text-yellow-600 dark:text-yellow-400';
                } elseif (in_array($notification->NOTIFICATION_TYPE, ['contract_expired', 'critical', 'error'])) {
                    $icon = 'exclamation-circle';
                    $colorClass = 'bg-red-100 dark:bg-red-900/30';
                    $iconColor = 'text-red-600 dark:text-red-400';
                } elseif ($notification->NOTIFICATION_TYPE === 'info') {
                    $icon = 'information-circle';
                    $colorClass = 'bg-blue-100 dark:bg-blue-900/30';
                    $iconColor = 'text-blue-600 dark:text-blue-400';
                } elseif ($notification->NOTIFICATION_TYPE === 'success') {
                    $icon = 'check-circle';
                    $colorClass = 'bg-green-100 dark:bg-green-900/30';
                    $iconColor = 'text-green-600 dark:text-green-400';
                }

                // Determine URL
                if ($notification->NOTIFIABLE_TYPE === 'App\\Models\\Contract' && $notification->NOTIFIABLE_ID) {
                    $contract = \App\Models\Contract::find($notification->NOTIFIABLE_ID);
                    $url = $contract && $contract->TCKT_ID 
                        ? route('tickets.show', $contract->TCKT_ID) 
                        : '#';
                } elseif ($notification->NOTIFIABLE_TYPE === 'App\\Models\\Ticket' && $notification->NOTIFIABLE_ID) {
                    $url = route('tickets.show', $notification->NOTIFIABLE_ID);
                } elseif (isset($notification->NOTIFICATION_DATA['ticket_id'])) {
                    $url = route('tickets.show', $notification->NOTIFICATION_DATA['ticket_id']);
                } elseif (isset($notification->NOTIFICATION_DATA['contract_id'])) {
                    $contract = \App\Models\Contract::find($notification->NOTIFICATION_DATA['contract_id']);
                    $url = $contract && $contract->TCKT_ID 
                        ? route('tickets.show', $contract->TCKT_ID) 
                        : '#';
                }
            @endphp

            <div 
                wire:key="notif-{{ $notification->LGL_ROW_ID }}"
                class="group relative flex gap-3 p-4 hover:bg-neutral-50 dark:hover:bg-neutral-800/50 {{ !$notification->isRead() ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}"
            >
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $colorClass }}">
                    <flux:icon :name="$icon" class="h-4 w-4 {{ $iconColor }}" />
                </div>
                
                <a href="{{ $url }}" wire:navigate class="flex-1 min-w-0 focus:outline-none">
                    <span class="absolute inset-0" aria-hidden="true"></span>
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ $notification->NOTIF_TITLE }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 line-clamp-2 md:line-clamp-none">{{ $notification->NOTIF_MSG }}</p>
                    <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">{{ $notification->created_at?->diffForHumans() ?? '-' }}</p>
                </a>

                @if(!$notification->isRead())
                <button 
                    wire:click="markAsRead({{ $notification->LGL_ROW_ID }})" 
                    class="relative z-10 shrink-0 text-neutral-400 hover:text-blue-600 dark:hover:text-blue-400"
                    title="Mark as read"
                >
                    <flux:icon name="check" class="h-4 w-4" />
                </button>
                @endif
            </div>
            @empty
            <div class="p-8 text-center text-neutral-500 dark:text-neutral-400">
                <flux:icon name="bell-slash" class="mx-auto h-8 w-8 text-neutral-300 dark:text-neutral-600" />
                <p class="mt-2">No notifications</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

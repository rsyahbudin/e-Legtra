<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <div class="me-5">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                    <x-app-logo />
                </a>
            </div>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                @if(auth()->user()->hasPermission('tickets.view') || auth()->user()->hasPermission('contracts.view'))
                <flux:navlist.group :heading="__('Ticketing System')" class="grid">
                    @if(auth()->user()->hasPermission('tickets.view'))
                    <flux:navlist.item icon="ticket" :href="route('tickets.index')" :current="request()->routeIs('tickets.*')" wire:navigate>{{ __('Tickets') }}</flux:navlist.item>
                    @endif
                    @if(auth()->user()->hasPermission('contracts.view'))
                    <flux:navlist.item icon="document-text" :href="route('contracts.repository')" :current="request()->routeIs('contracts.repository')" wire:navigate>{{ __('Contracts') }}</flux:navlist.item>
                    @endif
                </flux:navlist.group>
                @endif

                @if(auth()->user()->hasPermission('divisions.view'))
                <flux:navlist.group :heading="__('Management')" class="grid">
                    <flux:navlist.item icon="rectangle-group" :href="route('divisions.index')" :current="request()->routeIs('divisions.*')" wire:navigate>{{ __('Divisions') }}</flux:navlist.item>
                </flux:navlist.group>
                @endif

                @if(auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('roles.view') || auth()->user()->hasPermission('settings.view'))
                <flux:navlist.group :heading="__('Admin')" class="grid">
                    @if(auth()->user()->hasPermission('users.view'))
                    <flux:navlist.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>{{ __('Users') }}</flux:navlist.item>
                    @endif
                    @if(auth()->user()->hasPermission('roles.view'))
                    <flux:navlist.item icon="shield-check" :href="route('admin.roles.index')" :current="request()->routeIs('admin.roles.*')" wire:navigate>{{ __('Roles & Permissions') }}</flux:navlist.item>
                    @endif
                    @if(auth()->user()->hasPermission('settings.view'))
                    <flux:navlist.item icon="cog-6-tooth" :href="route('admin.settings.index')" :current="request()->routeIs('admin.settings.*')" wire:navigate>{{ __('Settings') }}</flux:navlist.item>
                    @endif
                    @if(auth()->user()->hasPermission('email_templates.edit'))
                    <flux:navlist.item icon="envelope" :href="route('admin.email-templates')" :current="request()->routeIs('admin.email-templates')" wire:navigate>{{ __('Email Templates') }}</flux:navlist.item>
                    @endif
                </flux:navlist.group>
                @endif
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                    data-test="sidebar-menu-button"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('appearance.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <!-- Notification Bell -->
            <livewire:components.notification-bell />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('appearance.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <!-- Top Navbar for Desktop -->
        <div class="hidden lg:block sticky top-0 z-40 border-b border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-end px-6 py-3">
                <livewire:components.notification-bell />
            </div>
        </div>

        {{ $slot }}

        {{-- Global Flash Toast Notifications --}}
        @if(session('success') || session('error') || session('warning') || session('info'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="fixed bottom-6 right-6 z-[9999] max-w-sm"
        >
            @if(session('success'))
            <div class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-5 py-4 shadow-lg dark:border-green-800 dark:bg-green-900/50">
                <flux:icon name="check-circle" class="h-5 w-5 text-green-600 dark:text-green-400 shrink-0" />
                <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                <button @click="show = false" class="ml-auto text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200">
                    <flux:icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
            @endif
            @if(session('error'))
            <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4 shadow-lg dark:border-red-800 dark:bg-red-900/50">
                <flux:icon name="exclamation-circle" class="h-5 w-5 text-red-600 dark:text-red-400 shrink-0" />
                <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
                <button @click="show = false" class="ml-auto text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200">
                    <flux:icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
            @endif
            @if(session('warning'))
            <div class="flex items-center gap-3 rounded-xl border border-yellow-200 bg-yellow-50 px-5 py-4 shadow-lg dark:border-yellow-800 dark:bg-yellow-900/50">
                <flux:icon name="exclamation-triangle" class="h-5 w-5 text-yellow-600 dark:text-yellow-400 shrink-0" />
                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">{{ session('warning') }}</p>
                <button @click="show = false" class="ml-auto text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-200">
                    <flux:icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
            @endif
            @if(session('info'))
            <div class="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 shadow-lg dark:border-blue-800 dark:bg-blue-900/50">
                <flux:icon name="information-circle" class="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0" />
                <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ session('info') }}</p>
                <button @click="show = false" class="ml-auto text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                    <flux:icon name="x-mark" class="h-4 w-4" />
                </button>
            </div>
            @endif
        </div>
        @endif

        @fluxScripts
    </body>
</html>

<?php

use App\Models\Division;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $ref_div_name = '';
    public string $ref_div_id = '';
    public string $ref_div_desc = '';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getDivisionsProperty()
    {
        return Division::query()
            ->when($this->search, fn($q) => $q->where('REF_DIV_NAME', 'like', "%{$this->search}%")
                ->orWhere('REF_DIV_ID', 'like', "%{$this->search}%"))
            ->withCount(['users', 'contracts', 'tickets'])
            ->orderBy('REF_DIV_NAME')
            ->paginate(10);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'ref_div_name', 'ref_div_id', 'ref_div_desc', 'is_active']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $division = Division::findOrFail($id);
        $this->editingId = $division->LGL_ROW_ID;
        $this->ref_div_name = $division->REF_DIV_NAME;
        $this->ref_div_id = $division->REF_DIV_ID;
        $this->ref_div_desc = $division->REF_DIV_DESC ?? '';
        $this->is_active = (bool) $division->IS_ACTIVE;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'ref_div_name' => ['required', 'string', 'max:255'],
            'ref_div_id' => ['required', 'string', 'max:20', $this->editingId ? "unique:LGL_DIVISION,REF_DIV_ID,{$this->editingId},LGL_ROW_ID" : 'unique:LGL_DIVISION,REF_DIV_ID'],
            'ref_div_desc' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $data = [
            'REF_DIV_NAME' => $validated['ref_div_name'],
            'REF_DIV_ID' => $validated['ref_div_id'],
            'REF_DIV_DESC' => $validated['ref_div_desc'],
            'IS_ACTIVE' => $validated['is_active'],
        ];

        if ($this->editingId) {
            Division::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Division successfully updated.');
        } else {
            Division::create($data);
            session()->flash('success', 'Division successfully added.');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user?->hasPermission('divisions.manage')) {
            return;
        }
        Division::findOrFail($id)->delete();
        session()->flash('success', 'Division successfully deleted.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Divisions</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Manage internal divisions list</p>
        </div>
        @if(auth()->user()?->hasPermission('divisions.manage'))
        <flux:button variant="primary" icon="plus" wire:click="create">Add Division</flux:button>
        @endif
    </div>

    <!-- Filters -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center bg-white p-4 rounded-xl border border-neutral-200 dark:bg-zinc-900 dark:border-neutral-700 shadow-sm">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search divisions..." icon="magnifying-glass" />
        </div>
        <!-- <div class="w-full sm:w-32">
             <flux:select wire:model.live="perPage">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </flux:select>
        </div> -->
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-6 py-3 font-medium">Code</th>
                        <th class="px-6 py-3 font-medium">Name</th>
                        <th class="px-6 py-3 font-medium">Description</th>
                        <!-- <th class="px-6 py-3 font-medium text-center">Users</th> -->
                        <th class="px-6 py-3 font-medium text-center">Ticket</th>
                        <th class="px-6 py-3 font-medium text-center">Contracts</th>
                        <th class="px-6 py-3 font-medium text-center">Status</th>
                        <th class="px-6 py-3 font-medium text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->divisions as $division)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="div-{{ $division->LGL_ROW_ID }}">
                        <td class="px-6 py-4 font-mono text-sm font-medium text-neutral-900 dark:text-white">
                            {{ $division->REF_DIV_ID }}
                        </td>
                        <td class="px-6 py-4 font-medium text-neutral-900 dark:text-white">
                            {{ $division->REF_DIV_NAME }}
                        </td>
                        <td class="px-6 py-4 truncate max-w-xs" title="{{ $division->REF_DIV_DESC }}">
                            {{ Str::limit($division->REF_DIV_DESC, 50) ?? '-' }}
                        </td>
                        <!-- <td class="px-6 py-4 text-center">
                            <flux:badge size="sm" color="zinc">{{ $division->users_count }}</flux:badge>
                        </td> -->
                        <td class="px-6 py-4 text-center">
                            <flux:badge size="sm" color="zinc">{{ $division->tickets_count }}</flux:badge>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <flux:badge size="sm" color="zinc">{{ $division->contracts_count }}</flux:badge>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($division->IS_ACTIVE)
                            <flux:badge size="sm" color="green" inset="top bottom">Active</flux:badge>
                            @else
                            <flux:badge size="sm" color="zinc" inset="top bottom">Inactive</flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-end">
                            <flux:dropdown>
                                <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" />
                                <flux:menu>
                                    @if(auth()->user()?->hasPermission('divisions.manage'))
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $division->LGL_ROW_ID }})">Edit</flux:menu.item>
                                    <flux:menu.item icon="squares-plus" wire:click="$dispatch('manage-sub-divisions', { divisionId: {{ $division->LGL_ROW_ID }} })">Manage Dept</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" wire:click="delete({{ $division->LGL_ROW_ID }})" wire:confirm="Are you sure?" variant="danger">Delete</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-neutral-500">
                            No divisions yet
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Showing <span class="font-medium">{{ $this->divisions->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $this->divisions->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $this->divisions->total() }}</span> results
                </p>
                <div class="w-full sm:w-auto">
                     {{ $this->divisions->links() }}
                </div>
            </div>
        </div>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? 'Edit Division' : 'Add Division' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Code</flux:label>
                    <flux:input wire:model="ref_div_id" placeholder="IT" required />
                    <flux:error name="ref_div_id" />
                </flux:field>
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="ref_div_name" required />
                    <flux:error name="ref_div_name" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="ref_div_desc" rows="2" />
            </flux:field>

            <flux:switch wire:model="is_active" label="Active" />
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
    
    <livewire:divisions.manage-sub-divisions />
</div>

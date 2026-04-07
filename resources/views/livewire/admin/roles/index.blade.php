<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showPermissionModal = false;
    public ?int $editingRoleId = null;
    public array $selectedPermissions = [];

    public string $name = '';
    public string $slug = '';
    public string $description = '';

    public function getRolesProperty()
    {
        return Role::withCount('permissions')
            ->when($this->search, fn($q) => $q->whereRaw('LOWER("ROLE_NAME") LIKE ?', ["%" . strtolower($this->search) . "%"]))
            ->orderBy('ROLE_NAME')
            ->get();
    }

    public function getPermissionsGroupedProperty()
    {
        return Permission::allGrouped();
    }

    /**
     * Computed property to fetch the role being edited for permissions.
     */
    public function getEditingRoleProperty(): ?Role
    {
        if (! $this->editingRoleId) {
            return null;
        }

        return Role::find($this->editingRoleId);
    }

    public function updatedName(string $value): void
    {
        if (! $this->editingId) {
            $this->slug = Str::slug($value);
        }
    }

    public function create(): void
    {
        abort_unless(Auth::user()?->hasPermission('roles.manage'), 403);

        $this->reset(['editingId', 'name', 'slug', 'description']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        abort_unless(Auth::user()?->hasPermission('roles.manage'), 403);

        $role = Role::findOrFail($id);
        $this->editingId = $role->ROLE_ID;
        $this->name = $role->ROLE_NAME;
        $this->slug = $role->ROLE_SLUG;
        $this->description = $role->ROLE_DESCRIPTION ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasPermission('roles.manage'), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $this->editingId ? "unique:LGL_ROLE,ROLE_SLUG,{$this->editingId},ROLE_ID" : 'unique:LGL_ROLE,ROLE_SLUG'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'slug.regex' => 'Slug must be lowercase letters, numbers, and dashes only.',
        ]);

        $roleData = [
            'ROLE_NAME' => $validated['name'],
            'ROLE_SLUG' => $validated['slug'],
            'ROLE_DESCRIPTION' => $validated['description'] ?? null,
            'REF_ROLE_UPDATED_BY' => Auth::id(),
        ];

        if ($this->editingId) {
            Role::findOrFail($this->editingId)->update($roleData);
            session()->flash('success', 'Role successfully updated.');
        } else {
            $roleData['REF_ROLE_CREATED_BY'] = Auth::id();
            Role::create($roleData);
            session()->flash('success', 'Role successfully added.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'slug', 'description']);
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()?->hasPermission('roles.manage'), 403);

        $role = Role::findOrFail($id);
        if ($role->is_system) {
            session()->flash('error', 'System role cannot be deleted.');
            return;
        }
        $role->delete();
        session()->flash('success', 'Role successfully deleted.');
    }

    public function editPermissions(int $id): void
    {
        abort_unless(Auth::user()?->hasPermission('roles.manage'), 403);

        $role = Role::findOrFail($id);
        $this->editingRoleId = $role->ROLE_ID;
        
        // Fetch permissions directly from pivot table for Oracle stability
        $this->selectedPermissions = \Illuminate\Support\Facades\DB::table('LGL_ROLE_PERMISSION')
            ->where('ROLE_ID', $this->editingRoleId)
            ->pluck('PERMISSION_ID')
            ->map(fn($id) => (string) $id)
            ->toArray();
            
        $this->showPermissionModal = true;
    }

    public function toggleGroupPermissions(string $group): void
    {
        abort_unless(Auth::user()?->hasPermission('roles.manage'), 403);

        $groupPermissions = Permission::active()
            ->where('PERMISSION_GROUP', $group)
            ->pluck('LGL_ROW_ID')
            ->map(fn($id) => (string) $id)
            ->toArray();

        $allSelected = empty(array_diff($groupPermissions, $this->selectedPermissions));

        if ($allSelected) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
        } else {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    public function savePermissions(): void
    {
        abort_unless(Auth::user()?->hasPermission('roles.manage'), 403);

        if ($this->editingRoleId) {
            $role = Role::findOrFail($this->editingRoleId);

            // Using direct DB operations for Oracle stability
            \Illuminate\Support\Facades\DB::transaction(function () use ($role) {
                // Remove existing
                \Illuminate\Support\Facades\DB::table('LGL_ROLE_PERMISSION')
                    ->where('ROLE_ID', $this->editingRoleId)
                    ->delete();

                // Safety: if editing super-admin role, ensure roles.manage is always kept
                // This prevents accidentally locking out the only admin role
                if ($role->ROLE_SLUG === 'super-admin') {
                    $managePerm = Permission::where('PERMISSION_CODE', 'roles.manage')->first();
                    if ($managePerm && ! in_array((string) $managePerm->LGL_ROW_ID, $this->selectedPermissions)) {
                        $this->selectedPermissions[] = (string) $managePerm->LGL_ROW_ID;
                    }
                }

                // Insert new ones
                $now = now();
                foreach ($this->selectedPermissions as $permId) {
                    \Illuminate\Support\Facades\DB::table('LGL_ROLE_PERMISSION')->insert([
                        'ROLE_ID' => $this->editingRoleId,
                        'PERMISSION_ID' => (int) $permId,
                        'REF_RP_CREATED_DT' => $now,
                        'REF_RP_CREATED_BY' => Auth::id(),
                    ]);
                }
            });

            session()->flash('success', 'Permissions for "' . $role->ROLE_NAME . '" successfully updated.');
        }
        $this->showPermissionModal = false;
        $this->editingRoleId = null;
        $this->selectedPermissions = [];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Roles & Permissions</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Manage roles and permissions access</p>
        </div>
        @if(auth()->user()?->hasPermission('roles.manage'))
        <flux:button variant="primary" icon="plus" wire:click="create">Add Role</flux:button>
        @endif
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search roles..." icon="magnifying-glass" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($this->roles as $role)
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900" wire:key="role-{{ $role->ROLE_ID }}">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-neutral-900 dark:text-white">{{ $role->ROLE_NAME }}</h3>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $role->ROLE_SLUG }}</p>
                </div>
                @if($role->is_system)
                <flux:badge color="amber">System</flux:badge>
                @endif
            </div>
            @if($role->ROLE_DESCRIPTION)
            <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $role->ROLE_DESCRIPTION }}</p>
            @endif
            <div class="mt-3 flex items-center justify-between">
                <span class="text-sm text-neutral-500">{{ $role->permissions_count }} permissions</span>
                @if(auth()->user()?->hasPermission('roles.manage'))
                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" icon="shield-check" wire:click="editPermissions({{ $role->ROLE_ID }})" title="Edit Permissions" />
                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $role->ROLE_ID }})" />
                    @if(!$role->is_system)
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $role->ROLE_ID }})" wire:confirm="Are you sure you want to delete this role?" />
                    @endif
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="col-span-full rounded-xl border border-neutral-200 bg-white p-12 text-center dark:border-neutral-700 dark:bg-zinc-900">
            <flux:icon name="shield-check" class="mx-auto h-12 w-12 text-neutral-300 dark:text-neutral-600" />
            <p class="mt-4 text-sm text-neutral-500 dark:text-neutral-400">No roles found</p>
        </div>
        @endforelse
    </div>

    <!-- Role Modal -->
    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? 'Edit Role' : 'Add Role' }}</flux:heading>
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model.live.debounce.300ms="name" required />
                <flux:error name="name" />
            </flux:field>
            <flux:field>
                <flux:label>Slug</flux:label>
                <flux:input wire:model="slug" placeholder="auto-generated-from-name" required />
                <flux:description>Unique identifier, use lowercase and dashes</flux:description>
                <flux:error name="slug" />
            </flux:field>
            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="2" />
                <flux:error name="description" />
            </flux:field>
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Permission Modal -->
    <flux:modal wire:model="showPermissionModal" class="w-full max-w-2xl">
        <div class="space-y-4">
            <flux:heading>Edit Permissions: {{ $this->editingRole?->ROLE_NAME }}</flux:heading>
            <div class="max-h-96 overflow-y-auto space-y-4">
                @foreach($this->permissionsGrouped as $group => $permissions)
                <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700" wire:key="perm-group-{{ $group }}">
                    <div class="mb-2 flex items-center justify-between">
                        <h4 class="font-medium capitalize text-neutral-900 dark:text-white">{{ $group }}</h4>
                        <button
                            type="button"
                            wire:click="toggleGroupPermissions('{{ $group }}')"
                            class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                            @php
                                $groupIds = $permissions->pluck('LGL_ROW_ID')->map(fn($id) => (string) $id)->toArray();
                                $allSelected = empty(array_diff($groupIds, $selectedPermissions));
                            @endphp
                            {{ $allSelected ? 'Deselect All' : 'Select All' }}
                        </button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($permissions as $permission)
                        <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-neutral-50 dark:hover:bg-zinc-800 cursor-pointer" wire:key="perm-{{ $permission->LGL_ROW_ID }}">
                            <input type="checkbox" value="{{ $permission->LGL_ROW_ID }}" wire:model="selectedPermissions" class="rounded border-neutral-300 text-blue-600 focus:ring-blue-500 dark:border-neutral-600 dark:bg-zinc-700">
                            <span class="text-neutral-700 dark:text-neutral-300">{{ $permission->PERMISSION_NAME }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showPermissionModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="primary" wire:click="savePermissions" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="savePermissions">Save Permissions</span>
                    <span wire:loading wire:target="savePermissions">Saving...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

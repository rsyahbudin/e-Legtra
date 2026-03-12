<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $user_id = '';
    public string $role_id = '';
    public string $division_id = '';
    public string $department_id = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getUsersProperty()
    {
        return User::with(['role', 'division', 'department'])
            ->when($this->search, fn($q) => $q->where('USER_FULLNAME', 'like', "%{$this->search}%")
                ->orWhere('USER_EMAIL', 'like', "%{$this->search}%"))
            ->orderBy('USER_FULLNAME')
            ->paginate(10);
    }

    public function getRolesProperty()
    {
        return Role::orderBy('ROLE_NAME')->get();
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('REF_DIV_NAME')->get();
    }

    public function getDepartmentsProperty()
    {
        if (!$this->division_id) {
            return collect();
        }
        return Department::where('DIV_ID', $this->division_id)->orderBy('REF_DEPT_NAME')->get();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'user_id', 'role_id', 'division_id', 'department_id']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->LGL_ROW_ID;
        $this->name = $user->USER_FULLNAME;
        $this->email = $user->USER_EMAIL;
        $this->password = '';
        $this->user_id = $user->USER_ID ?? '';
        $this->role_id = (string) ($user->USER_ROLE_ID ?? '');
        $this->division_id = (string) ($user->DIV_ID ?? '');
        $this->department_id = (string) ($user->DEPT_ID ?? '');
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', $this->editingId ? "unique:LGL_USER,USER_EMAIL,{$this->editingId},LGL_ROW_ID" : 'unique:LGL_USER,USER_EMAIL'],
            'user_id' => ['required', 'string', 'max:20', 'regex:/^[0-9]*$/', $this->editingId ? "unique:LGL_USER,USER_ID,{$this->editingId},LGL_ROW_ID" : 'unique:LGL_USER,USER_ID'],
            'role_id' => ['nullable', 'exists:LGL_ROLE,ROLE_ID'],
            'division_id' => ['nullable', 'exists:LGL_DIVISION,LGL_ROW_ID'],
            'department_id' => ['nullable', 'exists:LGL_DEPARTMENT,LGL_ROW_ID'],
        ];

        if (!$this->editingId || $this->password) {
            $rules['password'] = ['required', 'min:8'];
        }

        $validated = $this->validate($rules);

        $data = [
            'USER_FULLNAME' => $validated['name'],
            'USER_EMAIL' => $validated['email'],
            'USER_ID' => $validated['user_id'],
            'USER_ROLE_ID' => $validated['role_id'] ?: null,
            'DIV_ID' => $validated['division_id'] ?: null,
            'DEPT_ID' => $validated['department_id'] ?: null,
            'USER_UPDATED_BY' => Auth::id(),
        ];

        if (!empty($this->password)) {
            $data['USER_PASSWORD'] = $this->password;
        }

        if ($this->editingId) {
            User::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'User successfully updated.');
        } else {
            $data['USER_CREATED_BY'] = Auth::id();
            $data['USER_EMAIL_VERIFIED_DT'] = now();
            User::create($data);
            session()->flash('success', 'User successfully added.');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        if ($id === Auth::id()) {
            session()->flash('error', 'Cannot delete your own account.');
            return;
        }
        User::findOrFail($id)->delete();
        session()->flash('success', 'User successfully deleted.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Users</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Manage system users</p>
        </div>
        @if(auth()->user()?->hasPermission('users.manage'))
        <flux:button variant="primary" icon="plus" wire:click="create">Add User</flux:button>
        @endif
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search name or email..." icon="magnifying-glass" />
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Division</th>
                        <th class="px-4 py-3 text-left">Department</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->users as $user)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="user-{{ $user->LGL_ROW_ID }}">
                        <td class="px-4 py-3 font-medium text-neutral-900 dark:text-white">{{ $user->USER_FULLNAME }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->USER_EMAIL }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->USER_ID ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($user->role)
                            <flux:badge color="{{ $user->role->ROLE_SLUG === 'super-admin' ? 'red' : ($user->role->ROLE_SLUG === 'legal' ? 'blue' : 'zinc') }}">{{ $user->role->ROLE_NAME }}</flux:badge>
                            @else
                            <span class="text-neutral-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->division?->REF_DIV_NAME ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->department?->REF_DEPT_NAME ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @if(auth()->user()?->hasPermission('users.manage'))
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $user->LGL_ROW_ID }})" />
                                @if($user->LGL_ROW_ID !== auth()->id())
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $user->LGL_ROW_ID }})" wire:confirm="Are you sure?" />
                                @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-neutral-500">No users found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->users->hasPages())
        <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">{{ $this->users->links() }}</div>
        @endif
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? 'Edit User' : 'Add User' }}</flux:heading>
            
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" required />
                <flux:error name="name" />
            </flux:field>
            
            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input type="email" wire:model="email" required />
                <flux:error name="email" />
            </flux:field>
            
            <flux:field>
                <flux:label>ID / User ID</flux:label>
                <flux:input 
                    type="text" 
                    wire:model="user_id" 
                    maxlength="10"
                    pattern="[0-9]*"
                    inputmode="numeric"
                    placeholder="Enter 10-digit ID"
                />
                <flux:error name="user_id" />
            </flux:field>
            
            <flux:field>
                <flux:label>Password {{ $editingId ? '(leave blank to keep current)' : '' }}</flux:label>
                <flux:input type="password" wire:model="password" :required="!$editingId" />
                <flux:error name="password" />
            </flux:field>
            
            <flux:field>
                <flux:label>Role</flux:label>
                <flux:select wire:model="role_id">
                    <option value="">Select Role</option>
                    @foreach($this->roles as $role)
                    <option value="{{ $role->ROLE_ID }}">{{ $role->ROLE_NAME }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="role_id" />
            </flux:field>
            
            <flux:field>
                <flux:label>Division</flux:label>
                <flux:select wire:model.live="division_id">
                    <option value="">Select Division</option>
                    @foreach($this->divisions as $division)
                    <option value="{{ $division->LGL_ROW_ID }}">{{ $division->REF_DIV_NAME }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="division_id" />
            </flux:field>

            @if($this->division_id)
            <flux:field>
                <flux:label>Department</flux:label>
                <flux:select wire:model="department_id">
                    <option value="">Select Department (Optional)</option>
                    @if($this->departments->isNotEmpty())
                        @foreach($this->departments as $dept)
                        <option value="{{ $dept->LGL_ROW_ID }}">{{ $dept->REF_DEPT_NAME }}</option>
                        @endforeach
                    @else
                        <option value="" disabled>No departments</option>
                    @endif
                </flux:select>
                <flux:error name="department_id" />
            </flux:field>
            @endif
            
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

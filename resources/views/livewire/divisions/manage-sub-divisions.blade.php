<?php

use Livewire\Volt\Component;
use App\Models\Division;
use App\Models\Department;
use Livewire\Attributes\On;

new class extends Component {
    public ?Division $division = null;
    public $departments = [];
    
    public $ref_dept_name = '';
    public $ref_dept_id = '';
    public $email = '';
    public $cc_emails = '';
    public $editingId = null;
    public $showModal = false;
    public bool $is_active = true;

    #[On('manage-sub-divisions')]
    public function open($divisionId)
    {
        $this->division = Division::find($divisionId);
        $this->refreshList();
        $this->showModal = true;
        // Open the modal using Flux/Alpine logic if needed, or binding
        // Flux modals usually open via name. <flux:modal name="sub-divisions-modal" wire:model.self="showModal">
    }

    public function refreshList()
    {
        if ($this->division) {
            $this->departments = $this->division->departments()->orderBy('REF_DEPT_NAME')->get();
        } else {
            $this->departments = [];
        }
    }

    public function save()
    {
        $this->validate([
            'ref_dept_name' => 'required|string|max:255',
            'ref_dept_id' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'cc_emails' => 'nullable|string',
        ]);

        if ($this->editingId) {
            Department::find($this->editingId)->update([
                'REF_DEPT_NAME' => $this->ref_dept_name,
                'REF_DEPT_ID' => $this->ref_dept_id,
                'email' => $this->email,
                'cc_emails' => $this->cc_emails,
                'IS_ACTIVE' => $this->is_active,
            ]);
        } else {
            $this->division->departments()->create([
                'REF_DEPT_NAME' => $this->ref_dept_name,
                'REF_DEPT_ID' => $this->ref_dept_id,
                'email' => $this->email,
                'cc_emails' => $this->cc_emails,
                'IS_ACTIVE' => $this->is_active,
            ]);
        }

        $this->resetForm();
        $this->refreshList();
    }

    public function edit($id)
    {
        $dept = Department::find($id);
        $this->editingId = $dept->LGL_ROW_ID;
        $this->ref_dept_name = $dept->REF_DEPT_NAME;
        $this->ref_dept_id = $dept->REF_DEPT_ID;
        $this->email = $dept->email ?? '';
        $this->cc_emails = $dept->cc_emails;
        $this->is_active = (bool) $dept->IS_ACTIVE;
    }

    public function delete($id)
    {
        Department::find($id)->delete();
        $this->refreshList();
    }
    
    public function resetForm()
    {
        $this->reset(['ref_dept_name', 'ref_dept_id', 'email', 'cc_emails', 'editingId', 'is_active']);
        $this->is_active = true;
    }

    public function close()
    {
        $this->showModal = false;
        $this->resetForm();
    }
}; ?>

<flux:modal name="sub-divisions-modal" class="min-h-[500px] md:w-[600px]" wire:model="showModal">
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold">Manage Departments</h2>
            @if($division)
            <p class="text-sm text-neutral-500">{{ $division->REF_DIV_NAME }}</p>
            @endif
        </div>

        <!-- Form -->
        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Department Name</flux:label>
                        <flux:input wire:model="ref_dept_name" placeholder="Example: IT Support" required />
                        <flux:error name="ref_dept_name" />
                    </flux:field>
                    
                    <flux:field>
                        <flux:label>Code</flux:label>
                        <flux:input wire:model="ref_dept_id" placeholder="ITS" />
                        <flux:error name="ref_dept_id" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Department Email</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="department@example.com" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>CC Emails (for reminders)</flux:label>
                    <flux:textarea wire:model="cc_emails" rows="1" placeholder="email1@example.com, email2@example.com" />
                    <flux:description>Separate with commas.</flux:description>
                    <flux:error name="cc_emails" />
                </flux:field>

                <flux:switch wire:model="is_active" label="Active" />

                <div class="flex justify-end gap-2">
                    @if($editingId)
                    <flux:button wire:click="resetForm" size="sm" variant="ghost">Cancel</flux:button>
                    @endif
                    <flux:button type="submit" size="sm" variant="primary">{{ $editingId ? 'Update' : 'Add' }}</flux:button>
                </div>
            </form>
        </div>

        <!-- List -->
        <div class="space-y-2">
            <h3 class="font-medium">Department List</h3>
            @if(count($departments) > 0)
            <div class="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white dark:divide-neutral-700 dark:border-neutral-700 dark:bg-zinc-900">
                @foreach($departments as $dept)
                <div class="flex items-center justify-between p-3">
                    <div class="flex items-center gap-3">
                        <div>
                            <div class="font-medium">
                                {{ $dept->REF_DEPT_NAME }} 
                                <span class="text-xs text-neutral-500">({{ $dept->REF_DEPT_ID ?? '-' }}) | {{ $dept->email }}</span>
                            </div>
                            @if($dept->cc_emails)
                            <div class="text-xs text-neutral-500">{{ Str::limit(is_array($dept->cc_emails) ? implode(', ', $dept->cc_emails) : $dept->cc_emails, 50) }}</div>
                            @endif
                        </div>
                        @if($dept->IS_ACTIVE)
                            <flux:badge size="sm" color="green" inset="top bottom">Active</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc" inset="top bottom">Inactive</flux:badge>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <flux:button icon="pencil" size="xs" variant="ghost" wire:click="edit({{ $dept->LGL_ROW_ID }})" />
                        <flux:button icon="trash" size="xs" variant="danger" wire:click="delete({{ $dept->LGL_ROW_ID }})" wire:confirm="Delete this department?" />
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-4 text-center text-sm text-neutral-500">No departments yet.</div>
            @endif
        </div>
    </div>
</flux:modal>

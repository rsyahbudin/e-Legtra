<?php

use App\Models\{Department, Division};
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Log;

new #[Layout('components.layouts.app')] class extends Component {
    public $departments;
    public $divisions;
    public ?int $editingId = null;
    public bool $showModal = false;
    
    // Form fields
    public ?int $div_id = null;
    public string $ref_dept_name = '';
    public string $ref_dept_id = '';
    public string $email = '';
    public string $cc_emails_input = '';
    
    public function mount(): void
    {
        $this->loadDepartments();
        $this->divisions = Division::orderBy('REF_DIV_NAME')->get();
    }
    
    public function loadDepartments(): void
    {
        $this->departments = Department::with('division')
            ->orderBy('REF_DEPT_NAME')
            ->get();
    }
    
    public function create(): void
    {
        $this->reset(['editingId', 'div_id', 'ref_dept_name', 'ref_dept_id', 'email', 'cc_emails_input']);
        $this->showModal = true;
    }
    
    public function edit(int $id): void
    {
        $dept = Department::findOrFail($id);
        $this->editingId = $dept->LGL_ROW_ID;
        $this->div_id = $dept->DIV_ID;
        $this->ref_dept_name = $dept->REF_DEPT_NAME;
        $this->ref_dept_id = $dept->REF_DEPT_ID;
        $this->email = $dept->EMAIL ?? '';
        
        // Convert array to comma-separated string for editing
        $this->cc_emails_input = !empty($dept->CC_EMAILS) 
            ? implode(', ', $dept->CC_EMAILS) 
            : '';
            
        $this->showModal = true;
    }
    
    public function save(): void
    {
        $validated = $this->validate([
            'div_id' => ['required', 'exists:LGL_DIVISION,LGL_ROW_ID'],
            'ref_dept_name' => ['required', 'string', 'max:255'],
            'ref_dept_id' => ['required', 'string', 'max:20', $this->editingId ? "unique:LGL_DEPARTMENT,REF_DEPT_ID,{$this->editingId},LGL_ROW_ID" : 'unique:LGL_DEPARTMENT,REF_DEPT_ID'],
            'email' => ['nullable', 'email', 'max:100'],
            'cc_emails_input' => ['nullable', 'string'],
        ]);
        
        // Parse and validate CC emails
        $ccEmails = [];
        if (!empty($this->cc_emails_input)) {
            $emails = array_map('trim', explode(',', $this->cc_emails_input));
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ccEmails[] = $email;
                }
            }
        }
        
        $data = [
            'DIV_ID' => $validated['div_id'],
            'REF_DEPT_NAME' => $validated['ref_dept_name'],
            'REF_DEPT_ID' => $validated['ref_dept_id'],
            'EMAIL' => !empty($validated['email']) ? $validated['email'] : null,
            'CC_EMAILS' => !empty($ccEmails) ? $ccEmails : null,
        ];
        
        \Log::info('Saving department', [
            'editing_id' => $this->editingId,
            'input' => $this->cc_emails_input,
            'parsed_emails' => $ccEmails,
            'data' => $data,
        ]);
        
        if ($this->editingId) {
            $dept = Department::find($this->editingId);
            $dept->update($data);
            
            // Verify it was saved
            $dept->refresh();
            \Log::info('Department updated', [
                'id' => $dept->LGL_ROW_ID,
                'cc_emails_raw' => $dept->getRawOriginal('CC_EMAILS'),
                'cc_emails_cast' => $dept->CC_EMAILS,
            ]);
            
            $this->dispatch('notify', type: 'success', message: 'Department updated successfully!');
        } else {
            $dept = Department::create($data);
            \Log::info('Department created', ['id' => $dept->LGL_ROW_ID, 'cc_emails' => $dept->CC_EMAILS]);
            $this->dispatch('notify', type: 'success', message: 'Department added successfully!');
        }
        
        $this->showModal = false;
        $this->loadDepartments();
    }
    
    public function delete(int $id): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (!$user?->hasPermission('departments.manage')) {
            return;
        }

        Department::findOrFail($id)->delete();
        $this->dispatch('notify', type: 'success', message: 'Department deleted successfully!');
        $this->loadDepartments();
    }
} ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Departments</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Manage departments and CC email lists</p>
        </div>
        
        @if(auth()->user()?->hasPermission('departments.manage'))
        <flux:button wire:click="create" icon="plus">
            Add Department
        </flux:button>
        @endif
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Division</th>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Code</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">CC Emails</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($departments as $dept)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800">
                        <td class="px-4 py-3 text-sm text-neutral-900 dark:text-white">
                            {{ $dept->division->REF_DIV_NAME }}
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-neutral-900 dark:text-white">
                            {{ $dept->REF_DEPT_NAME }}
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            <code class="rounded bg-neutral-100 px-2 py-1 dark:bg-neutral-800">{{ $dept->REF_DEPT_ID }}</code>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            @if($dept->EMAIL)
                                <a href="mailto:{{ $dept->EMAIL }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ $dept->EMAIL }}</a>
                            @else
                                <span class="text-neutral-400 dark:text-neutral-500">No email</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            @if(!empty($dept->CC_EMAILS))
                                <div class="flex flex-wrap gap-1">
                                    @foreach($dept->CC_EMAILS as $email)
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ $email }}
                                    </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-neutral-400 dark:text-neutral-500">No CC emails</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @if(auth()->user()?->hasPermission('departments.manage'))
                                <flux:button wire:click="edit({{ $dept->LGL_ROW_ID }})" size="sm" variant="ghost" icon="pencil" />
                                @endif
                                
                                @if(auth()->user()?->hasPermission('departments.manage'))
                                <flux:button wire:click="delete({{ $dept->LGL_ROW_ID }})" wire:confirm="Are you sure you want to delete this department?" size="sm" variant="ghost" icon="trash" class="text-red-600 hover:text-red-700" />
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                            No departments found. Create one to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <flux:modal name="department-modal" :open="$showModal" @closed="$wire.showModal = false">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit' : 'Add' }} Department</flux:heading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Division *</flux:label>
                    <flux:select wire:model="div_id" name="div_id">
                        <option value="">-- Select Division --</option>
                        @foreach($divisions as $division)
                        <option value="{{ $division->LGL_ROW_ID }}">{{ $division->REF_DIV_NAME }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="div_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Name *</flux:label>
                    <flux:input wire:model="ref_dept_name" name="ref_dept_name" />
                    <flux:error name="ref_dept_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Code *</flux:label>
                    <flux:input wire:model="ref_dept_id" name="ref_dept_id" />
                    <flux:error name="ref_dept_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Department Email</flux:label>
                    <flux:input wire:model="email" name="email" type="email" placeholder="department@example.com" />
                    <flux:description>Primary email address for this department</flux:description>
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>CC Email Addresses</flux:label>
                    <flux:textarea 
                        wire:model="cc_emails_input" 
                        name="cc_emails_input"
                        rows="3"
                        placeholder="email1@example.com, email2@example.com"
                    />
                    <flux:description>Enter email addresses separated by commas. These will receive CC when tickets are created.</flux:description>
                    <flux:error name="cc_emails_input" />
                </flux:field>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button type="button" variant="ghost" @click="$wire.showModal = false">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Update' : 'Create' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

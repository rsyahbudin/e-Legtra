<?php

use App\Models\Setting;
use App\Services\EmailTemplateService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    // Ticket Created
    public $ticket_created_subject = '';
    public $ticket_created_body = '';

    // Ticket Status Changed
    public $ticket_status_subject = '';
    public $ticket_status_body = '';

    // Contract Status Changed
    public $contract_status_subject = '';
    public $contract_status_body = '';

    // Contract Reminder
    public $contract_reminder_subject = '';
    public $contract_reminder_body = '';

    public function mount(): void
    {
        // Check permission
        if (!auth()->user()->hasPermission('email_templates.edit')) {
            abort(403, 'Unauthorized access');
        }

        $service = app(EmailTemplateService::class);

        // Load ticket created template
        $ticketCreated = $service->getTicketCreatedTemplate();
        $this->ticket_created_subject = $ticketCreated['subject'];
        $this->ticket_created_body = $ticketCreated['body'];

        // Load ticket status changed template
        $ticketStatus = $service->getTicketStatusChangedTemplate();
        $this->ticket_status_subject = $ticketStatus['subject'];
        $this->ticket_status_body = $ticketStatus['body'];

        // Load contract status changed template
        $contractStatus = $service->getContractStatusChangedTemplate();
        $this->contract_status_subject = $contractStatus['subject'];
        $this->contract_status_body = $contractStatus['body'];

        // Load contract reminder template
        $contractReminder = $service->getContractReminderTemplate();
        $this->contract_reminder_subject = $contractReminder['subject'];
        $this->contract_reminder_body = $contractReminder['body'];
    }

    public function save(): void
    {
        // Save email templates
        Setting::set('ticket_created_email_subject', $this->ticket_created_subject);
        Setting::set('ticket_created_email_body', $this->ticket_created_body);

        Setting::set('ticket_status_changed_email_subject', $this->ticket_status_subject);
        Setting::set('ticket_status_changed_email_body', $this->ticket_status_body);

        Setting::set('contract_status_changed_email_subject', $this->contract_status_subject);
        Setting::set('contract_status_changed_email_body', $this->contract_status_body);

        Setting::set('contract_reminder_email_subject', $this->contract_reminder_subject);
        Setting::set('contract_reminder_email_body', $this->contract_reminder_body);

        $this->dispatch('notify', type: 'success', message: 'Email templates and logo saved successfully!');
        $this->mount(); // Reload
    }

    public function resetToDefaults(): void
    {
        $service = app(EmailTemplateService::class);

        // Reset to actual default values from service
        $ticketCreated = $service->getTicketCreatedTemplate();
        $ticketStatus = $service->getTicketStatusChangedTemplate();
        $contractStatus = $service->getContractStatusChangedTemplate();
        $contractReminder = $service->getContractReminderTemplate();

        Setting::set('ticket_created_email_subject', 'New Ticket: {ticket_number}');
        Setting::set('ticket_created_email_body', "Dear Sir/Madam,\n\nWe would like to inform you that a new ticket has been created with the following details:\n\nTicket Number: {ticket_number}\nDocument Title: {proposed_document_title}\nDocument Type: {document_type}\nCreated by: {creator_name}\nDivision: {division_name}\nDepartment: {department_name}\nDate: {created_at}\n\nWe kindly request your review of this ticket in your dashboard at your earliest convenience.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary");
        
        Setting::set('ticket_status_changed_email_subject', 'Ticket {ticket_number} Status Changed');
        Setting::set('ticket_status_changed_email_body', "Dear Sir/Madam,\n\nWe would like to inform you that the status of ticket {ticket_number} has been updated:\n\nTicket Number: {ticket_number}\nDocument Title: {proposed_document_title}\nDocument Type: {document_type}\nPrevious Status: {old_status}\nNew Status: {new_status}\nChanged by: {reviewed_by}\nDate: {reviewed_at}\n{rejection_reason}\n\nPlease review the updated ticket details in your dashboard.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary");
        
        Setting::set('contract_status_changed_email_subject', 'Contract {contract_number} Status Changed');
        Setting::set('contract_status_changed_email_body', "Dear Sir/Madam,\n\nWe would like to inform you that the status of contract {contract_number} has been updated:\n\nContract Number: {contract_number}\nAgreement Name: {agreement_name}\nDocument Type: {document_type}\nPrevious Status: {old_status}\nNew Status: {new_status}\nStart Date: {start_date}\nEnd Date: {end_date}\n{termination_reason}\n\nPlease review the updated contract details in your dashboard.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary");
        
        Setting::set('contract_reminder_email_subject', 'Agreement {agreement_name} – Expiration Reminder');
        Setting::set('contract_reminder_email_body', "Dear Sir/Madam,\n\nWe would like to inform you that Agreement {agreement_name} will expire on {end_date}.\n\nIn this regard, we kindly request your confirmation regarding the extension of the said agreement. Should you wish to proceed with the renewal, please contact us at legal@pfimegalife.co.id. Otherwise, kindly disregard this reminder.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary");

        $this->dispatch('notify', type: 'success', message: 'Email templates restored to defaults!');
        $this->mount(); // Reload with defaults
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Email Templates</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Customize email templates and company logo for system emails</p>
    </div>

    <form wire:submit="save" class="space-y-6">

        <!-- Ticket Created Template -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Ticket Created Email</h2>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Subject</flux:label>
                    <flux:input wire:model="ticket_created_subject" placeholder="Email subject line" />
                    <flux:error name="ticket_created_subject" />
                </flux:field>

                <flux:field>
                    <flux:label>Body</flux:label>
                    <flux:textarea wire:model="ticket_created_body" rows="8" />
                    <flux:description>
                        Available placeholders: {ticket_number}, {proposed_document_title}, {document_type}, {creator_name}, {division_name}, {department_name}, {created_at}
                    </flux:description>
                    <flux:error name="ticket_created_body" />
                </flux:field>
            </div>
        </div>

        <!-- Ticket Status Changed Template -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Ticket Status Changed Email</h2>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Subject</flux:label>
                    <flux:input wire:model="ticket_status_subject" />
                    <flux:error name="ticket_status_subject" />
                </flux:field>

                <flux:field>
                    <flux:label>Body</flux:label>
                    <flux:textarea wire:model="ticket_status_body" rows="8" />
                    <flux:description>
                        Available placeholders: {ticket_number}, {proposed_document_title}, {old_status}, {new_status}, {reviewed_by}, {reviewed_at}, {rejection_reason}
                    </flux:description>
                    <flux:error name="ticket_status_body" />
                </flux:field>
            </div>
        </div>

        <!-- Contract Status Changed Template -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Contract Status Changed Email</h2>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Subject</flux:label>
                    <flux:input wire:model="contract_status_subject" />
                    <flux:error name="contract_status_subject" />
                </flux:field>

                <flux:field>
                    <flux:label>Body</flux:label>
                    <flux:textarea wire:model="contract_status_body" rows="8" />
                    <flux:description>
                        Available placeholders: {contract_number}, {agreement_name}, {old_status}, {new_status}, {start_date}, {end_date}, {termination_reason}
                    </flux:description>
                    <flux:error name="contract_status_body" />
                </flux:field>
            </div>
        </div>

        <!-- Contract Reminder Template -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Contract Reminder Email</h2>
            
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Subject</flux:label>
                    <flux:input wire:model="contract_reminder_subject" />
                    <flux:error name="contract_reminder_subject" />
                </flux:field>

                <flux:field>
                    <flux:label>Body</flux:label>
                    <flux:textarea wire:model="contract_reminder_body" rows="8" />
                    <flux:description>
                        Available placeholders: {contract_number}, {agreement_name}, {counterpart_name}, {end_date}, {days_remaining}
                    </flux:description>
                    <flux:error name="contract_reminder_body" />
                </flux:field>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
            <flux:button type="submit" variant="primary">Save All Changes</flux:button>
            <flux:button 
                type="button" 
                wire:click="resetToDefaults" 
                wire:confirm="Are you sure you want to reset all email templates to their default values? This will overwrite your current customizations." 
                variant="ghost"
            >
                Reset to Defaults
            </flux:button>
        </div>
    </form>
</div>

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add email template settings using the existing settings table structure
        $emailSettings = [
            [
                'key' => 'legal_team_email',
                'value' => 'legal@pfimegalife.co.id',
                'type' => 'string',
                'description' => 'Email tim legal untuk menerima notifikasi ticket baru',
            ],
            [
                'key' => 'ticket_created_email_subject',
                'value' => 'New Ticket: {ticket_number} - {proposed_document_title}',
                'type' => 'string',
                'description' => 'Email subject for new tickets. Placeholders: {ticket_number}, {proposed_document_title}, {creator_name}, {division_name}',
            ],
            [
                'key' => 'ticket_created_email_body',
                'value' => "Dear Sir/Madam,\n\nWe would like to inform you that a new ticket has been created with the following details:\n\nTicket Number: {ticket_number}\nDocument Title: {proposed_document_title}\nDocument Type: {document_type}\nCreated by: {creator_name}\nDivision: {division_name}\nDepartment: {department_name}\nDate: {created_at}\n\nWe kindly request your review of this ticket in your dashboard at your earliest convenience.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary",
                'type' => 'text',
                'description' => 'Email body for new tickets. Placeholders: {ticket_number}, {proposed_document_title}, {document_type}, {creator_name}, {division_name}, {department_name}, {created_at}',
            ],
            [
                'key' => 'ticket_status_changed_email_subject',
                'value' => 'Ticket {ticket_number} Status Changed: {new_status}',
                'type' => 'string',
                'description' => 'Email subject for ticket status changes. Placeholders: {ticket_number}, {old_status}, {new_status}, {proposed_document_title}',
            ],
            [
                'key' => 'ticket_status_changed_email_body',
                'value' => "Dear Sir/Madam,\n\nWe would like to inform you that the status of ticket {ticket_number} has been updated:\n\nTicket Number: {ticket_number}\nDocument Title: {proposed_document_title}\nDocument Type: {document_type}\nPrevious Status: {old_status}\nNew Status: {new_status}\nChanged by: {reviewed_by}\nDate: {reviewed_at}\n{rejection_reason}\n\nPlease review the updated ticket details in your dashboard.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary",
                'type' => 'text',
                'description' => 'Email body for ticket status changes. Placeholders: {ticket_number}, {proposed_document_title}, {document_type}, {old_status}, {new_status}, {reviewed_by}, {reviewed_at}, {rejection_reason}',
            ],
            [
                'key' => 'contract_status_changed_email_subject',
                'value' => 'Contract {contract_number} Status Changed: {new_status}',
                'type' => 'string',
                'description' => 'Email subject for contract status changes. Placeholders: {contract_number}, {old_status}, {new_status}, {agreement_name}',
            ],
            [
                'key' => 'contract_status_changed_email_body',
                'value' => "Dear Sir/Madam,\n\nWe would like to inform you that the status of contract {contract_number} has been updated:\n\nContract Number: {contract_number}\nAgreement Name: {agreement_name}\nDocument Type: {document_type}\nPrevious Status: {old_status}\nNew Status: {new_status}\nStart Date: {start_date}\nEnd Date: {end_date}\n{termination_reason}\n\nPlease review the updated contract details in your dashboard.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary",
                'type' => 'text',
                'description' => 'Email body for contract status changes. Placeholders: {contract_number}, {agreement_name}, {document_type}, {old_status}, {new_status}, {start_date}, {end_date}, {termination_reason}',
            ],
            [
                'key' => 'contract_reminder_email_subject',
                'value' => 'Agreement {agreement_name} – Expiration Reminder',
                'type' => 'string',
                'description' => 'Email subject for contract expiration reminders. Placeholders: {contract_number}, {agreement_name}, {days_remaining}',
            ],
            [
                'key' => 'contract_reminder_email_body',
                'value' => "Dear Sir/Madam,\n\nWe would like to inform you that Agreement {agreement_name} will expire on {end_date}.\n\nIn this regard, we kindly request your confirmation regarding the extension of the said agreement. Should you wish to proceed with the renewal, please contact us at legal@pfimegalife.co.id. Otherwise, kindly disregard this reminder.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary",
                'type' => 'text',
                'description' => 'Email body for contract expiration reminders. Placeholders: {contract_number}, {agreement_name}, {counterpart_name}, {end_date}, {days_remaining}',
            ],
        ];

        foreach ($emailSettings as $setting) {
            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        $keys = [
            'legal_team_email',
            'ticket_created_email_subject',
            'ticket_created_email_body',
            'ticket_status_changed_email_subject',
            'ticket_status_changed_email_body',
            'contract_status_changed_email_subject',
            'contract_status_changed_email_body',
            'contract_reminder_email_subject',
            'contract_reminder_email_body',
        ];

        DB::table('settings')->whereIn('key', $keys)->delete();
    }
};

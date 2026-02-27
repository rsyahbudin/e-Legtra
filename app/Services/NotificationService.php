<?php

namespace App\Services;

use App\Mail\DynamicMail;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private EmailTemplateService $templateService
    ) {}

    /**
     * Notify when a new ticket is created.
     */
    public function notifyTicketCreated(Ticket $ticket): void
    {
        // Ensure relationships are loaded
        $ticket->load(['creator', 'division', 'department']);

        $template = $this->templateService->getTicketCreatedTemplate();

        $data = [
            'ticket_number' => $ticket->TCKT_NO,
            'proposed_document_title' => $ticket->getAnswer('proposed_document_title') ?? '-',
            'document_type' => $ticket->document_type_label,
            'creator_name' => $ticket->creator->USER_FULLNAME ?? $ticket->creator->name,
            'creator_email' => $ticket->creator->USER_EMAIL ?? $ticket->creator->email,
            'division_name' => $ticket->division->REF_DIV_NAME ?? $ticket->division->name,
            'department_name' => $ticket->department?->REF_DEPT_NAME ?? $ticket->department?->name ?? '-',
            'created_at' => $ticket->TCKT_CREATED_DT->format('d M Y H:i'),
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        // Get legal department email
        $legalEmail = Department::getLegalEmail();

        try {
            // Get department email and CC emails
            $departmentEmail = $ticket->department?->email;
            $deptCcEmails = [];

            if ($ticket->department && ! empty($ticket->department->cc_emails)) {
                $deptCcEmails = array_filter($ticket->department->cc_emails_list, function ($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }

            // TO: Legal Department
            $to = $legalEmail;

            // CC: Creator + Creator Department + Creator Dept CC Emails
            $cc = [$ticket->creator->email];

            if ($departmentEmail && filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $departmentEmail;
            }

            if (! empty($deptCcEmails)) {
                $cc = array_merge($cc, $deptCcEmails);
            }

            // Remove duplicates
            $cc = array_unique(array_filter($cc));

            Log::info('Sending ticket created email', [
                'ticket_no' => $ticket->TCKT_NO,
                'to' => $to,
                'cc' => $cc,
            ]);

            // Send email
            if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $mailable = new DynamicMail($subject, $body, $data);

                if (! empty($cc)) {
                    Mail::to($to)->cc(array_values($cc))->send($mailable);
                } else {
                    Mail::to($to)->send($mailable);
                }

                Log::info('Ticket created email sent successfully', ['ticket_no' => $ticket->TCKT_NO]);
            } else {
                Log::warning('No valid legal email found', ['ticket_no' => $ticket->TCKT_NO]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket created email', [
                'ticket_no' => $ticket->TCKT_NO,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - let ticket creation succeed even if email fails
        }

        // Create notification for legal users
        $this->createNotificationForLegalTeam(
            "New ticket #{$ticket->TCKT_NO} requires review",
            $ticket,
            [$ticket->TCKT_CREATED_BY, auth()->user()?->LGL_ROW_ID]
        );
    }

    /**
     * Notify when ticket status changes.
     */
    public function notifyTicketStatusChanged(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        $template = $this->templateService->getTicketStatusChangedTemplate();

        $data = [
            'ticket_number' => $ticket->TCKT_NO,
            'proposed_document_title' => $ticket->getAnswer('proposed_document_title') ?? '-',
            'document_type' => $ticket->document_type_label,
            'old_status' => $this->getStatusLabel($oldStatus),
            'new_status' => $this->getStatusLabel($newStatus),
            'reviewed_by' => $ticket->reviewer?->USER_FULLNAME ?? $ticket->reviewer?->name ?? 'System',
            'reviewed_at' => $ticket->TCKT_REVIEWED_DT?->format('d M Y H:i') ?? now()->format('d M Y H:i'),
            'rejection_reason' => $ticket->TCKT_REJECT_REASON ? "\nAlasan Penolakan: {$ticket->TCKT_REJECT_REASON}" : '',
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        try {
            // TO: Creator
            $to = $ticket->creator->email;

            // Prepare CC list
            $cc = [];

            // Get legal department email and CC emails
            $legalEmail = Department::getLegalEmail();
            $legalDept = Department::where('REF_DEPT_ID', 'LEGAL')->orWhere('REF_DEPT_NAME', 'LIKE', '%Legal%')->first();

            if ($legalEmail && filter_var($legalEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $legalEmail;
            }

            // Add Legal Department CC Emails
            if ($legalDept && ! empty($legalDept->cc_emails)) {
                $legalCcEmails = array_filter($legalDept->cc_emails_list, function ($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
                $cc = array_merge($cc, $legalCcEmails);
            }

            // Get creator department email and CC emails
            $departmentEmail = $ticket->department?->email;

            if ($departmentEmail && filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $departmentEmail;
            }

            // Add Creator Department CC Emails
            if ($ticket->department && ! empty($ticket->department->cc_emails)) {
                $deptCcEmails = array_filter($ticket->department->cc_emails_list, function ($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
                $cc = array_merge($cc, $deptCcEmails);
            }

            // Remove duplicates and filter out the creator (TO recipient)
            $cc = array_unique(array_filter($cc, function ($email) use ($to) {
                return $email !== $to;
            }));

            // Send email
            $mailable = new DynamicMail($subject, $body, $data);

            if (! empty($cc)) {
                Mail::to($to)->cc(array_values($cc))->send($mailable);
            } else {
                Mail::to($to)->send($mailable);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket status change email', [
                'ticket_no' => $ticket->TCKT_NO,
                'error' => $e->getMessage(),
            ]);
        }

        // Create notifications
        $this->createNotificationForUser(
            $ticket->creator,
            "Ticket #{$ticket->TCKT_NO} status changed: {$this->getStatusLabel($newStatus)}",
            $ticket
        );

        $this->createNotificationForLegalTeam(
            "Ticket #{$ticket->TCKT_NO} status changed: {$this->getStatusLabel($newStatus)}",
            $ticket,
            [$ticket->TCKT_CREATED_BY, auth()->user()?->LGL_ROW_ID]
        );
    }

    /**
     * Notify when contract status changes.
     */
    public function notifyContractStatusChanged(Contract $contract, string $oldStatus, string $newStatus): void
    {
        $template = $this->templateService->getContractStatusChangedTemplate();

        $data = [
            'contract_number' => $contract->CONTR_NO,
            'agreement_name' => $contract->CONTR_AGREE_NAME,
            'document_type' => $contract->document_type_label,
            'old_status' => $this->getStatusLabel($oldStatus),
            'new_status' => $this->getStatusLabel($newStatus),
            'start_date' => $contract->CONTR_START_DT?->format('d M Y') ?? '-',
            'end_date' => $contract->CONTR_END_DT?->format('d M Y') ?? '-',
            'termination_reason' => $contract->CONTR_TERMINATE_REASON ? "\nAlasan Terminasi: {$contract->CONTR_TERMINATE_REASON}" : '',
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        // Send notification
        if ($contract->creator) {
            try {
                // TO: Creator
                $to = $contract->creator->email;

                // Prepare CC list
                $cc = [];

                // Get legal department email and CC emails
                $legalEmail = Department::getLegalEmail();
                $legalDept = Department::where('REF_DEPT_ID', 'LEGAL')->orWhere('REF_DEPT_NAME', 'LIKE', '%Legal%')->first();

                if ($legalEmail && filter_var($legalEmail, FILTER_VALIDATE_EMAIL)) {
                    $cc[] = $legalEmail;
                }

                // Add Legal Department CC Emails
                if ($legalDept && ! empty($legalDept->cc_emails)) {
                    $legalCcEmails = array_filter($legalDept->cc_emails_list, function ($email) {
                        return filter_var($email, FILTER_VALIDATE_EMAIL);
                    });
                    $cc = array_merge($cc, $legalCcEmails);
                }

                // Get contract department email and CC emails
                $departmentEmail = $contract->department?->email;

                if ($departmentEmail && filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
                    $cc[] = $departmentEmail;
                }

                // Add Contract Department CC Emails
                if ($contract->department && ! empty($contract->department->cc_emails)) {
                    $deptCcEmails = array_filter($contract->department->cc_emails_list, function ($email) {
                        return filter_var($email, FILTER_VALIDATE_EMAIL);
                    });
                    $cc = array_merge($cc, $deptCcEmails);
                }

                // Remove duplicates and filter out the creator (TO recipient)
                $cc = array_unique(array_filter($cc, function ($email) use ($to) {
                    return $email !== $to;
                }));

                // Send email
                $mailable = new DynamicMail($subject, $body, $data);

                if (! empty($cc)) {
                    Mail::to($to)->cc(array_values($cc))->send($mailable);
                } else {
                    Mail::to($to)->send($mailable);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send contract status change email', [
                    'contract_no' => $contract->CONTR_NO,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Create notifications
        if ($contract->creator) {
            $this->createNotificationForUser(
                $contract->creator,
                "Contract #{$contract->CONTR_NO} status changed: {$newStatus}",
                $contract
            );
        }

        $this->createNotificationForLegalTeam(
            "Contract #{$contract->CONTR_NO} status changed: {$newStatus}",
            $contract,
            [$contract->CONTR_CREATED_BY, auth()->user()?->LGL_ROW_ID]
        );
    }

    /**
     * Create notification for a specific user.
     */
    private function createNotificationForUser(User $user, string $message, $notifiable): void
    {
        $user->internalNotifications()->create([
            'NOTIFICATION_TYPE' => 'info',
            'NOTIF_TITLE' => $message,
            'NOTIF_MSG' => $message,
            'NOTIFIABLE_TYPE' => get_class($notifiable),
            'NOTIFIABLE_ID' => $notifiable->LGL_ROW_ID,
        ]);
    }

    /**
     * Create notification for all legal team users.
     */
    private function createNotificationForLegalTeam(string $message, $notifiable, array $excludeUserIds = []): void
    {
        $legalUsers = User::getAdminAndLegalUsers();

        foreach ($legalUsers as $user) {
            if (in_array($user->LGL_ROW_ID, $excludeUserIds)) {
                continue;
            }

            $this->createNotificationForUser($user, $message, $notifiable);
        }
    }

    /**
     * Get human-readable status label from database.
     */
    private function getStatusLabel(string $status): string
    {
        // Try to get status name from database
        $ticketStatus = \App\Models\TicketStatus::where('LOV_VALUE', $status)->first();

        if ($ticketStatus) {
            return $ticketStatus->LOV_DISPLAY_NAME; // English name from database
        }

        // Fallback to ucfirst if not found
        return ucfirst(str_replace('_', ' ', $status));
    }
}

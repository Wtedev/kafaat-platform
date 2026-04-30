<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailLogService
{
    /**
     * Send a notification to a user and write a log entry.
     * Failures are caught and logged — the caller is never thrown an exception.
     *
     * @param  string  $templateKey  Identifies the notification type for reporting.
     * @param  User|null  $sentBy    The staff/admin who triggered the action, or null for system.
     */
    public function send(
        User $recipient,
        Notification $notification,
        string $templateKey,
        string $subject,
        ?User $sentBy = null,
    ): void {
        try {
            $recipient->notify($notification);

            EmailLog::create([
                'recipient_email' => $recipient->email,
                'subject'         => $subject,
                'template_key'    => $templateKey,
                'status'          => 'sent',
                'sent_by'         => $sentBy?->id,
                'sent_at'         => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send notification email', [
                'template_key'    => $templateKey,
                'recipient_email' => $recipient->email,
                'error'           => $e->getMessage(),
            ]);

            EmailLog::create([
                'recipient_email' => $recipient->email,
                'subject'         => $subject,
                'template_key'    => $templateKey,
                'status'          => 'failed',
                'sent_by'         => $sentBy?->id,
                'sent_at'         => now(),
            ]);
        }
    }
}

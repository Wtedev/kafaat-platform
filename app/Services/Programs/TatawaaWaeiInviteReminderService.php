<?php

namespace App\Services\Programs;

use App\Mail\TatawaaWaeiInviteMail;
use App\Models\EmailLog;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\TatawaaWaeiInviteMail as Invite;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class TatawaaWaeiInviteReminderService
{
    /**
     * @param  list<array{email?: string, name?: string}>|null  $recipients
     * @return array{
     *   total: int,
     *   eligible: int,
     *   skipped_registered: int,
     *   sent: int,
     *   failed: int,
     *   stopped_for_quota: bool,
     *   failures: list<array{email: string, error: string}>
     * }
     */
    public function send(bool $dryRun = false, ?array $recipients = null, ?string $recipientsAbsolutePath = null): array
    {
        $recipients ??= $this->loadRecipients($recipientsAbsolutePath);
        $registeredEmails = $this->registeredEmailsForProgram(
            array_column($recipients, 'email'),
        );

        $eligible = [];
        $skippedRegistered = 0;
        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            if (isset($registeredEmails[$email])) {
                $skippedRegistered++;
                continue;
            }
            $eligible[] = [
                'email' => $email,
                'name' => trim((string) ($recipient['name'] ?? '')),
            ];
        }

        $report = [
            'total' => count($recipients),
            'eligible' => count($eligible),
            'skipped_registered' => $skippedRegistered,
            'sent' => 0,
            'failed' => 0,
            'stopped_for_quota' => false,
            'failures' => [],
        ];

        if ($dryRun) {
            return $report;
        }

        foreach ($eligible as $recipient) {
            try {
                Mail::to($recipient['email'], $recipient['name'] !== '' ? $recipient['name'] : null)
                    ->send(new TatawaaWaeiInviteMail($recipient['name'], reminder: true));

                EmailLog::query()->create([
                    'recipient_email' => $recipient['email'],
                    'subject' => Invite::REMINDER_SUBJECT,
                    'template_key' => Invite::REMINDER_TEMPLATE_KEY,
                    'status' => 'sent',
                    'sent_by' => null,
                    'sent_at' => now(),
                ]);

                $report['sent']++;
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                EmailLog::query()->create([
                    'recipient_email' => $recipient['email'],
                    'subject' => Invite::REMINDER_SUBJECT,
                    'template_key' => Invite::REMINDER_TEMPLATE_KEY,
                    'status' => 'failed',
                    'sent_by' => null,
                    'sent_at' => now(),
                ]);

                $report['failed']++;
                if (count($report['failures']) < 25) {
                    $report['failures'][] = [
                        'email' => $recipient['email'],
                        'error' => mb_substr($message, 0, 240),
                    ];
                }

                if ($this->isDailyQuotaExceeded($message)) {
                    $report['stopped_for_quota'] = true;
                    break;
                }
            }

            usleep(150_000);
        }

        return $report;
    }

    /**
     * @return list<array{email: string, name: string}>
     */
    public function loadRecipients(?string $absolutePath = null): array
    {
        $path = $absolutePath;
        if ($path === null) {
            $path = Storage::disk('local')->path(Invite::RECIPIENTS_STORAGE_PATH);
        }

        if (! is_file($path)) {
            throw new \RuntimeException('Invite recipients file is missing: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            return [];
        }

        return $this->normalizeRecipients($decoded);
    }

    /**
     * @param  list<array{email?: string, name?: string}|mixed>  $decoded
     * @return list<array{email: string, name: string}>
     */
    public function normalizeRecipients(array $decoded): array
    {
        $out = [];
        $seen = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $out[] = [
                'email' => $email,
                'name' => trim((string) ($row['name'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string|null>  $emails
     * @return array<string, true>
     */
    public function registeredEmailsForProgram(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(
            fn ($email) => strtolower(trim((string) $email)),
            $emails,
        ))));

        if ($emails === []) {
            return [];
        }

        $programId = TrainingProgram::query()
            ->where('slug', Invite::PROGRAM_SLUG)
            ->value('id');

        if ($programId === null) {
            return [];
        }

        $userIds = User::query()
            ->whereIn('email', $emails)
            ->pluck('id', 'email');

        if ($userIds->isEmpty()) {
            return [];
        }

        $registeredUserIds = ProgramRegistration::query()
            ->where('training_program_id', $programId)
            ->whereIn('user_id', $userIds->values()->all())
            ->pluck('user_id')
            ->all();

        $registered = [];
        foreach ($userIds as $email => $userId) {
            if (in_array($userId, $registeredUserIds, true)) {
                $registered[strtolower((string) $email)] = true;
            }
        }

        return $registered;
    }

    private function isDailyQuotaExceeded(string $message): bool
    {
        $lower = strtolower($message);

        return str_contains($lower, 'daily_quota_exceeded')
            || str_contains($lower, 'daily email sending quota');
    }
}

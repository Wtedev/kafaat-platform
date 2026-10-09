<?php

namespace App\Jobs;

use App\Enums\RegistrationStatus;
use App\Models\EmailLog;
use App\Models\ProgramRegistration;
use App\Notifications\DataForumTelegramReminder as ReminderMail;
use App\Support\DataForumAcceptance;
use App\Support\DataForumTelegramReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SendDataForumTelegramReminderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 25;

    public int $timeout = 30;

    public function __construct(
        public readonly int $registrationId,
    ) {}

    public function handle(): void
    {
        if (RateLimiter::tooManyAttempts('data-forum-telegram-reminder', 2)) {
            $this->release(max(1, RateLimiter::availableIn('data-forum-telegram-reminder')));

            return;
        }

        $registration = ProgramRegistration::query()
            ->with(['user.profile', 'trainingProgram'])
            ->find($this->registrationId);

        $user = $registration?->user;
        if ($registration === null || $user === null || blank($user->email)) {
            return;
        }

        if (! DataForumAcceptance::matches($registration->trainingProgram)) {
            return;
        }

        if ($registration->status !== RegistrationStatus::Approved) {
            return;
        }

        if ($registration->approved_at === null || $registration->approved_at->greaterThan(DataForumTelegramReminder::cutoff())) {
            return;
        }

        $alreadySent = EmailLog::query()
            ->where('recipient_email', $user->email)
            ->where('template_key', DataForumTelegramReminder::TEMPLATE_KEY)
            ->where('status', 'sent')
            ->exists();

        if ($alreadySent) {
            return;
        }

        RateLimiter::hit('data-forum-telegram-reminder', 1);

        try {
            $user->notifyNow(new ReminderMail(DataForumTelegramReminder::whenWord()));

            EmailLog::query()->create([
                'recipient_email' => $user->email,
                'subject' => DataForumTelegramReminder::SUBJECT,
                'template_key' => DataForumTelegramReminder::TEMPLATE_KEY,
                'status' => 'sent',
                'sent_by' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries && $this->shouldRetry($exception)) {
                $this->release(15);

                return;
            }

            EmailLog::query()->create([
                'recipient_email' => $user->email,
                'subject' => DataForumTelegramReminder::SUBJECT,
                'template_key' => DataForumTelegramReminder::TEMPLATE_KEY,
                'status' => 'failed',
                'sent_by' => null,
                'sent_at' => now(),
            ]);
        }
    }

    private function shouldRetry(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, '429')
            || str_contains($message, 'too many')
            || str_contains($message, 'rate');
    }
}

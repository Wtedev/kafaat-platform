<?php

namespace App\Console\Commands;

use App\Jobs\SendDataForumTelegramReminderJob;
use App\Models\EmailLog;
use App\Support\DataForumTelegramReminder;
use Illuminate\Console\Command;

class SendDataForumTelegramRemindersCommand extends Command
{
    protected $signature = 'data-forum:send-telegram-reminders {--dry-run : Count the first batch without sending}';

    protected $description = 'Queue one Telegram reminder email for each first-batch approved data-forum registrant.';

    public function handle(): int
    {
        $registrations = DataForumTelegramReminder::cohortQuery()
            ->with('user:id,email')
            ->orderBy('id')
            ->get();

        $alreadySent = EmailLog::query()
            ->where('template_key', DataForumTelegramReminder::TEMPLATE_KEY)
            ->where('status', 'sent')
            ->pluck('recipient_email');

        $pending = $registrations->filter(function ($registration) use ($alreadySent): bool {
            $email = $registration->user?->email;

            return filled($email) && ! $alreadySent->contains($email);
        })->values();

        $this->info('cohort='.$registrations->count().' pending='.$pending->count().' already_sent='.($registrations->count() - $pending->count()));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        foreach ($pending as $index => $registration) {
            SendDataForumTelegramReminderJob::dispatch($registration->id)
                ->delay(now()->addMilliseconds($index * 600));
        }

        $this->info('queued='.$pending->count());

        return self::SUCCESS;
    }
}

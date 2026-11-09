<?php

namespace App\Jobs;

use App\Services\Programs\TatawaaWaeiInviteReminderService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTatawaaWaeiInviteRemindersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public int $uniqueFor = 7200;

    /**
     * @param  list<array{email: string, name: string}>  $recipients
     */
    public function __construct(
        public readonly array $recipients,
    ) {}

    public function uniqueId(): string
    {
        return 'tatawaa-waei-invite-reminders';
    }

    public function handle(TatawaaWaeiInviteReminderService $reminders): void
    {
        $report = $reminders->send(dryRun: false, recipients: $this->recipients);

        Log::info('tatawaa_waei.invite_reminders.finished', $report);
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\SendTatawaaWaeiInviteRemindersJob;
use App\Services\Programs\TatawaaWaeiInviteReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTatawaaWaeiInviteRemindersCommand extends Command
{
    protected $signature = 'tatawaa-waei:send-invite-reminders
        {--dry-run : Count eligible recipients without sending}
        {--sync : Send immediately in this process}
        {--queue : Dispatch a queue job to send now}
        {--path= : Absolute path to recipients JSON}
        {--at= : Delay dispatch until this Asia/Riyadh datetime (Y-m-d H:i)}';

    protected $description = 'Send تطوع واعي registration invite reminders, skipping people already registered.';

    public function handle(TatawaaWaeiInviteReminderService $reminders): int
    {
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : null;
        $recipients = $reminders->loadRecipients($path);

        $at = $this->option('at');
        if (is_string($at) && trim($at) !== '') {
            $when = Carbon::parse($at, config('app.timezone', 'Asia/Riyadh'));
            SendTatawaaWaeiInviteRemindersJob::dispatch($recipients)->delay($when);
            $this->info('Queued invite reminders for '.$when->timezone(config('app.timezone'))->toDateTimeString().' ('.count($recipients).' recipients).');

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            SendTatawaaWaeiInviteRemindersJob::dispatch($recipients);
            $this->info('Queued invite reminders for immediate processing ('.count($recipients).' recipients).');

            return self::SUCCESS;
        }

        $shouldSend = (bool) $this->option('sync');
        $report = $reminders->send(
            dryRun: (bool) $this->option('dry-run') || ! $shouldSend,
            recipients: $recipients,
        );

        if (! $shouldSend && ! $this->option('dry-run')) {
            $this->warn('No --sync/--queue/--at provided; ran dry-run only.');
        }

        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (($report['stopped_for_quota'] ?? false) === true) {
            $this->error('Stopped early to preserve Resend daily quota reserve.');

            return self::FAILURE;
        }

        return ($report['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}

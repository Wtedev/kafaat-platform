<?php

namespace App\Console\Commands;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DispatchTrainingInboxMilestonesCommand extends Command
{
    protected $signature = 'inbox:dispatch-training-milestones';

    protected $description = 'يرسل تنبيهات الوارد لفتح/إغلاق التسجيل وبدء/انتهاء البرامج المنشورة (يوم التقويم الحالي، مرة واحدة لكل حدث).';

    public function handle(InboxNotificationService $inbox): int
    {
        $today = now()->startOfDay();

        TrainingProgram::query()
            ->where('status', ProgramStatus::Published)
            ->chunkById(50, function ($programs) use ($today, $inbox): void {
                foreach ($programs as $program) {
                    $this->maybeRegistrationOpen($program, $today, $inbox);
                    $this->maybeRegistrationClose($program, $today, $inbox);
                    $this->maybeRunStart($program, $today, $inbox);
                    $this->maybeRunEnd($program, $today, $inbox);
                }
            });

        return self::SUCCESS;
    }

    private function maybeRegistrationOpen(TrainingProgram $program, Carbon $today, InboxNotificationService $inbox): void
    {
        if ($program->learning_path_id !== null) {
            return;
        }

        $d = $program->registration_start;
        if ($d === null || ! $d->isSameDay($today)) {
            return;
        }

        $key = "inbox:milestone:program:{$program->id}:reg_open:{$today->toDateString()}";
        if (Cache::has($key)) {
            return;
        }

        $inbox->registrationWindowOpenedForProgram($program);
        Cache::forever($key, true);
    }

    private function maybeRegistrationClose(TrainingProgram $program, Carbon $today, InboxNotificationService $inbox): void
    {
        if ($program->learning_path_id !== null) {
            return;
        }

        $d = $program->registration_end;
        if ($d === null || ! $d->isSameDay($today)) {
            return;
        }

        $key = "inbox:milestone:program:{$program->id}:reg_close:{$today->toDateString()}";
        if (Cache::has($key)) {
            return;
        }

        $inbox->registrationWindowClosedForProgram($program);
        Cache::forever($key, true);
    }

    private function maybeRunStart(TrainingProgram $program, Carbon $today, InboxNotificationService $inbox): void
    {
        $d = $program->start_date;
        if ($d === null || ! $d->isSameDay($today)) {
            return;
        }

        $key = "inbox:milestone:program:{$program->id}:run_start:{$today->toDateString()}";
        if (Cache::has($key)) {
            return;
        }

        $inbox->trainingRunStartedForProgram($program);
        Cache::forever($key, true);
    }

    private function maybeRunEnd(TrainingProgram $program, Carbon $today, InboxNotificationService $inbox): void
    {
        $d = $program->end_date;
        if ($d === null || ! $d->isSameDay($today)) {
            return;
        }

        $key = "inbox:milestone:program:{$program->id}:run_end:{$today->toDateString()}";
        if (Cache::has($key)) {
            return;
        }

        $inbox->trainingRunEndedForProgram($program);
        Cache::forever($key, true);
    }
}

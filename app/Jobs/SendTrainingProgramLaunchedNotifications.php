<?php

namespace App\Jobs;

use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTrainingProgramLaunchedNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $programId,
        public readonly ?int $publisherId = null,
    ) {}

    public function handle(InboxNotificationService $inbox): void
    {
        $program = TrainingProgram::query()->find($this->programId);

        if ($program === null) {
            return;
        }

        $publisher = $this->publisherId !== null
            ? User::query()->find($this->publisherId)
            : null;

        try {
            $inbox->programLaunched($program, $publisher);
        } catch (\Throwable $e) {
            Log::error('فشل إرسال تنبيهات إطلاق البرنامج (مهمة خلفية).', [
                'program_id' => $this->programId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

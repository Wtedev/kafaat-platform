<?php

namespace App\Jobs;

use App\Models\LearningPath;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendLearningPathLaunchedNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $pathId,
        public readonly ?int $publisherId = null,
    ) {}

    public function handle(InboxNotificationService $inbox): void
    {
        $path = LearningPath::query()->find($this->pathId);

        if ($path === null) {
            return;
        }

        $publisher = $this->publisherId !== null
            ? User::query()->find($this->publisherId)
            : null;

        try {
            $inbox->learningPathLaunched($path, $publisher);
        } catch (\Throwable $e) {
            Log::error('فشل إرسال تنبيهات إطلاق المسار (مهمة خلفية).', [
                'path_id' => $this->pathId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

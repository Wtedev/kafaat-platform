<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Services\News\NewsPublicationService;
use Illuminate\Console\Command;

class PublishScheduledNewsCommand extends Command
{
    protected $signature = 'news:publish-scheduled';

    protected $description = 'لا يُظهر الخبر للزوار — هذا الأمر يرسل فقط تنبيهات الوارد للأخبار التي أصبح وقت نشرها (published_at) مستحقاً ولم يُرسل لها التنبيه بعد. ظهور الخبر على الموقع العام يحدث تلقائياً عند الطلب حسب published_at دون الحاجة لهذا الأمر.';

    public function handle(NewsPublicationService $publication): int
    {
        $count = 0;

        News::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('published_notification_sent_at')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($publication, &$count): void {
                foreach ($chunk as $news) {
                    if ($publication->sendPublishedNotificationIfNeeded($news, null)) {
                        $count++;
                    }
                }
            });

        $this->info("Sent publication notifications for {$count} news item(s).");

        return self::SUCCESS;
    }
}

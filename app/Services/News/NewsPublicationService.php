<?php

namespace App\Services\News;

use App\Models\News;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Centralizes news publish / draft / schedule and idempotent inbox notifications.
 */
final class NewsPublicationService
{
    public function __construct(
        private readonly InboxNotificationService $inbox,
    ) {}

    /**
     * رسالة موحّدة لرفض موعد نشر في الماضي أو عند اللحظة الحالية (واجهة الإدارة + الخدمة).
     */
    public static function schedulePublishMustBeFutureMessage(): string
    {
        return 'لا يُعتمد موعد نشر في الماضي أو مطابقاً للوقت الحالي. إن أردت إتاحة الخبر للجمهور الآن فاستخدم «نشر الآن»، وإن أردت الجدولة فاختر تاريخاً ووقتاً لاحقين عن اللحظة الحالية وفق توقيت المنصة.';
    }

    /**
     * @param  User  $actor  المحرّر الحالي (يُستخدم التحقق من الجلسة عبر Auth في خطاف النموذج).
     */
    public function publishNow(News $news, User $actor): void
    {
        $news->published_at = now();
        $news->save();
    }

    /**
     * Clears publish time and inbox notification marker so a later publish counts as
     * a new cycle and may send the notification again (once per cycle).
     */
    public function moveToDraft(News $news): void
    {
        $news->published_at = null;
        $news->published_notification_sent_at = null;
        $news->save();
    }

    public function schedule(News $news, Carbon $publishAt): void
    {
        if ($publishAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'publish_at' => self::schedulePublishMustBeFutureMessage(),
            ]);
        }

        $news->published_at = $publishAt;
        $news->save();
    }

    /**
     * Sends the published notification once per publication cycle (tracked by published_notification_sent_at).
     *
     * @return bool true if a notification was sent
     */
    public function sendPublishedNotificationIfNeeded(News $news, ?User $publisher = null): bool
    {
        $news->refresh();

        if (! $news->shouldSendPublishedNotification()) {
            return false;
        }

        $this->inbox->newsPublished($news, $publisher ?? (Auth::user() instanceof User ? Auth::user() : null));

        $news->published_notification_sent_at = now();
        $news->saveQuietly();

        return true;
    }
}

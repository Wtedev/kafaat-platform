<?php

namespace App\Services\Privacy;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Inbox\NotificationMessage;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;

final class PrivacyRequestNotificationService
{
    public function __construct(
        private readonly InboxNotificationService $inbox,
    ) {}

    public function notifyRequestCreated(User $user, PrivacyRequest $request): void
    {
        $this->dispatch(
            $user,
            'تم استلام طلب الخصوصية',
            sprintf(
                'استلمنا طلب %s. المرجع: %s',
                $request->request_type->label(),
                $request->uuid,
            ),
        );
    }

    public function notifyStatusChange(User $user, PrivacyRequest $request, ?string $message = null): void
    {
        $text = $message ?? match ($request->status) {
            PrivacyRequestStatus::UnderReview => 'طلب الخصوصية قيد المراجعة.',
            PrivacyRequestStatus::Approved => 'تمت الموافقة على طلب الخصوصية.',
            PrivacyRequestStatus::PartiallyApproved => 'تمت الموافقة جزئياً على طلب الخصوصية.',
            PrivacyRequestStatus::Rejected => 'تم رفض طلب الخصوصية.',
            PrivacyRequestStatus::Completed => 'اكتملت معالجة طلب الخصوصية.',
            PrivacyRequestStatus::Cancelled => 'تم إلغاء طلب الخصوصية.',
            default => 'تحديث على طلب الخصوصية.',
        };

        $this->dispatch($user, 'تحديث طلب الخصوصية', $text);
    }

    public function notifyExportReady(User $user, PrivacyRequest $request): void
    {
        $this->dispatch(
            $user,
            'ملف تصدير بياناتك جاهز',
            'يمكنك تنزيل نسخة بياناتك من مركز الخصوصية قبل انتهاء الصلاحية.',
        );
    }

    public function notifyExportFailed(User $user, PrivacyRequest $request): void
    {
        $this->dispatch(
            $user,
            'تعذّر تجهيز ملف التصدير',
            'تعذّر تجهيز ملف تصدير بياناتك. يرجى مراجعة مركز الخصوصية أو التواصل مع الدعم.',
        );
    }

    private function dispatch(User $user, string $title, string $message): void
    {
        $this->inbox->dispatch(
            new NotificationMessage(
                type: InboxNotificationType::UserAlert,
                title: $title,
                message: $message,
                senderId: null,
                targetType: NotificationTargetType::SingleUser,
                context: ['portal_route' => 'portal.settings.profile'],
                emailable: true,
            ),
            [$user->id],
        );
    }
}

<?php

namespace App\Services\Inbox;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Enums\RegistrationStatus;
use App\Inbox\NotificationMessage;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\News;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InboxNotificationService
{
    public function unreadCount(User $user): int
    {
        return InboxNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->count();
    }

    public function markAsRead(InboxNotification $notification, User $actor): void
    {
        if ((int) $notification->user_id !== (int) $actor->id) {
            return;
        }

        $notification->markAsRead();
    }

    public function markAllAsRead(User $user): int
    {
        return InboxNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * @return Collection<int, InboxNotification>
     */
    public function latestForUser(User $user, int $limit = 50): Collection
    {
        return InboxNotification::query()
            ->where('user_id', $user->id)
            ->with('sender')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function dispatch(NotificationMessage $message, iterable $recipientUserIds): void
    {
        $ids = collect($recipientUserIds)->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $rows = $message->toRows($ids->all());

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table((new InboxNotification)->getTable())->insert($chunk);
        }
    }

    public function programLaunched(TrainingProgram $program, ?User $publisher = null): void
    {
        $ids = $this->resolveRecipientIds(NotificationTargetType::AllPortalUsers);
        $msg = new NotificationMessage(
            type: InboxNotificationType::ProgramLaunched,
            title: 'إطلاق برنامج: '.$program->title,
            message: 'تم نشر برنامج تدريبي جديد. يمكنك الاطلاع على التفاصيل والتسجيل من صفحة البرامج.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::AllPortalUsers,
        );
        $this->dispatch($msg, $ids);
    }

    public function programUpdatedForRegistrants(TrainingProgram $program, ?User $editor = null): void
    {
        $ids = $program->registrations()
            ->whereIn('status', [
                RegistrationStatus::Pending->value,
                RegistrationStatus::Approved->value,
            ])
            ->pluck('user_id')
            ->unique()
            ->all();

        if ($ids === []) {
            return;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::ProgramUpdated,
            title: 'تحديث بخصوص برنامج «'.$program->title.'»',
            message: 'تم تحديث معلومات البرنامج الذي سجّلت فيه. يُرجى مراجعة الصفحة للاطلاع على آخر التفاصيل.',
            senderId: $editor?->id,
            targetType: NotificationTargetType::ProgramRegistrants,
        );
        $this->dispatch($msg, $ids);
    }

    public function newsPublished(News $news, ?User $publisher = null): void
    {
        $ids = $this->resolveRecipientIds(NotificationTargetType::AllPortalUsers);
        $excerpt = $news->excerpt ?? '';
        $msg = new NotificationMessage(
            type: InboxNotificationType::NewsPublished,
            title: 'نشر خبر: '.$news->title,
            message: $excerpt !== '' ? $excerpt : 'تم نشر خبر جديد على المنصة.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::AllPortalUsers,
        );
        $this->dispatch($msg, $ids);
    }

    public function registrationApprovedProgram(User $recipient, TrainingProgram $program, ?User $approver = null): void
    {
        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationApproved,
            title: 'تم قبول تسجيلك',
            message: 'تم قبول طلبك في البرنامج التدريبي «'.$program->title.'».',
            senderId: $approver?->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function registrationRejectedProgram(User $recipient, TrainingProgram $program, ?string $reason = null, ?User $rejector = null): void
    {
        $body = 'لم يتم قبول طلبك في البرنامج التدريبي «'.$program->title.'».';
        if ($reason !== null && $reason !== '') {
            $body .= "\nالسبب: ".$reason;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationRejected,
            title: 'تحديث طلب التسجيل',
            message: $body,
            senderId: $rejector?->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function registrationApprovedPath(User $recipient, LearningPath $path, ?User $approver = null): void
    {
        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationApproved,
            title: 'تم قبول تسجيلك',
            message: 'تم قبول طلبك في اللقاء / المسار «'.$path->title.'».',
            senderId: $approver?->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function registrationRejectedPath(User $recipient, LearningPath $path, ?string $reason = null, ?User $rejector = null): void
    {
        $body = 'لم يتم قبول طلبك في اللقاء / المسار «'.$path->title.'».';
        if ($reason !== null && $reason !== '') {
            $body .= "\nالسبب: ".$reason;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationRejected,
            title: 'تحديث طلب التسجيل',
            message: $body,
            senderId: $rejector?->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function registrationApprovedVolunteer(User $recipient, VolunteerOpportunity $opportunity, ?User $approver = null): void
    {
        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationApproved,
            title: 'تم قبول تسجيلك',
            message: 'تم قبول طلبك في الفرصة التطوعية «'.$opportunity->title.'».',
            senderId: $approver?->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function registrationRejectedVolunteer(User $recipient, VolunteerOpportunity $opportunity, ?string $reason = null, ?User $rejector = null): void
    {
        $body = 'لم يتم قبول طلبك في الفرصة التطوعية «'.$opportunity->title.'».';
        if ($reason !== null && $reason !== '') {
            $body .= "\nالسبب: ".$reason;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationRejected,
            title: 'تحديث طلب التسجيل',
            message: $body,
            senderId: $rejector?->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function certificateIssued(User $recipient, Model $certificateable, ?User $issuedBy = null): void
    {
        $label = match (true) {
            $certificateable instanceof TrainingProgram => $certificateable->title,
            $certificateable instanceof LearningPath => $certificateable->title,
            $certificateable instanceof VolunteerOpportunity => $certificateable->title,
            default => 'نشاطك',
        };

        $msg = new NotificationMessage(
            type: InboxNotificationType::CertificateIssued,
            title: 'صدور شهادة',
            message: 'صدرت شهادتك المتعلقة بـ «'.$label.'». يمكنك استعراضها من قسم الشهادات في بوابتك.',
            senderId: $issuedBy?->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function volunteerOpportunityUpdated(VolunteerOpportunity $opportunity, ?User $editor = null): void
    {
        $registrantIds = $opportunity->registrations()
            ->whereNotIn('status', [
                RegistrationStatus::Rejected->value,
                RegistrationStatus::Cancelled->value,
            ])
            ->pluck('user_id');

        $volunteerIds = User::query()
            ->role('volunteer')
            ->pluck('id');

        $ids = $registrantIds->merge($volunteerIds)->unique()->values()->all();

        if ($ids === []) {
            return;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::VolunteerOpportunityUpdated,
            title: 'تحديث فرصة تطوعية: '.$opportunity->title,
            message: 'تم تحديث تفاصيل الفرصة التطوعية. يُرجى مراجعة الصفحة للاطلاع على آخر المعلومات.',
            senderId: $editor?->id,
            targetType: NotificationTargetType::DirectRecipients,
        );
        $this->dispatch($msg, $ids);
    }

    public function generalMessage(User $sender, NotificationTargetType $audience, string $title, string $body): void
    {
        $ids = $this->resolveRecipientIds($audience);
        $msg = new NotificationMessage(
            type: InboxNotificationType::GeneralMessage,
            title: $title,
            message: $body,
            senderId: $sender->id,
            targetType: $audience,
        );
        $this->dispatch($msg, $ids);
    }

    /**
     * تنبيه موجّه من مستخدم (يظهر العنوان بصيغة «تنبيه من {الاسم}» عند الحاجة).
     */
    public function userAlert(User $from, User $to, string $title, string $body): void
    {
        $msg = new NotificationMessage(
            type: InboxNotificationType::UserAlert,
            title: 'تنبيه من '.$from->name.' — '.$title,
            message: $body,
            senderId: $from->id,
            targetType: NotificationTargetType::SingleUser,
        );
        $this->dispatch($msg, [$to->id]);
    }

    /**
     * @return list<int>
     */
    public function resolveRecipientIds(NotificationTargetType $audience): array
    {
        $q = User::query()->where('is_active', true);

        return match ($audience) {
            NotificationTargetType::AllPortalUsers => $q->where(function ($sub): void {
                $sub->whereIn('role_type', ['trainee', 'beneficiary', 'volunteer'])
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['trainee', 'volunteer']));
            })->pluck('id')->all(),

            NotificationTargetType::Trainees => $q->where(function ($sub): void {
                $sub->whereIn('role_type', ['trainee', 'beneficiary'])
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'trainee'));
            })->pluck('id')->all(),

            NotificationTargetType::Volunteers => $q->where(function ($sub): void {
                $sub->where('role_type', 'volunteer')
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'volunteer'));
            })->pluck('id')->all(),

            NotificationTargetType::Staff => $q->where(function ($sub): void {
                $sub->where('role_type', 'staff')
                    ->orWhere('role_type', 'admin')
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', [
                        'admin', 'media_pr', 'media_employee', 'pr_employee', 'training_manager', 'volunteering_manager', 'staff',
                    ]));
            })->pluck('id')->all(),

            NotificationTargetType::SingleUser,
            NotificationTargetType::ProgramRegistrants,
            NotificationTargetType::DirectRecipients,
            NotificationTargetType::VolunteerTeamMembers => [],
        };
    }

    /**
     * رسالة عامة يدوية إلى قائمة مستلمين محددة (بعد التحقق من صلاحية المُرسِل).
     *
     * @param  iterable<int>  $recipientUserIds
     */
    public function manualGeneral(
        User $sender,
        string $title,
        string $body,
        iterable $recipientUserIds,
        NotificationTargetType $storedTarget,
    ): void {
        $msg = new NotificationMessage(
            type: InboxNotificationType::GeneralMessage,
            title: $title,
            message: $body,
            senderId: $sender->id,
            targetType: $storedTarget,
        );
        $this->dispatch($msg, $recipientUserIds);
    }
}

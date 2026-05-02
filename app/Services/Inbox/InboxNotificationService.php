<?php

namespace App\Services\Inbox;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Enums\RegistrationStatus;
use App\Inbox\NotificationMessage;
use App\Models\Certificate;
use App\Models\InboxNotification;
use App\Models\LearningPath;
use App\Models\News;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
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

    /**
     * @return array{resource: string, id: int}
     */
    private static function inboxContext(string $resource, int $id): array
    {
        return ['resource' => $resource, 'id' => $id];
    }

    public function programLaunched(TrainingProgram $program, ?User $publisher = null): void
    {
        $staffIds = collect($this->resolveStaffUserIds());
        $portalIds = collect($this->resolveRecipientIds(NotificationTargetType::AllPortalUsers));
        $beneficiaryIds = $portalIds->diff($staffIds)->values()->all();

        $msg = new NotificationMessage(
            type: InboxNotificationType::ProgramLaunched,
            title: 'إطلاق برنامج: '.$program->title,
            message: 'تم نشر برنامج تدريبي جديد. يمكنك الاطلاع على التفاصيل والتسجيل من صفحة البرامج.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::AllPortalUsers,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($msg, $beneficiaryIds);

        $staffMsg = new NotificationMessage(
            type: InboxNotificationType::StaffTrainingEntityCreated,
            title: 'برنامج تدريبي جديد: '.$program->title,
            message: 'تم إنشاء أو نشر برنامج تدريبي جديد على المنصة.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($staffMsg, $this->resolveAdminAndTrainingManagerIds());
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
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($msg, $ids);
    }

    public function newsPublished(News $news, ?User $publisher = null): void
    {
        $staffIds = collect($this->resolveStaffUserIds());
        $portalIds = collect($this->resolveRecipientIds(NotificationTargetType::AllPortalUsers));
        $beneficiaryIds = $portalIds->diff($staffIds)->values()->all();

        $excerpt = $news->excerpt ?? '';
        $body = $excerpt !== '' ? $excerpt : 'تم نشر خبر جديد على المنصة.';

        $msgBeneficiary = new NotificationMessage(
            type: InboxNotificationType::NewsPublished,
            title: 'نشر خبر: '.$news->title,
            message: $body,
            senderId: $publisher?->id,
            targetType: NotificationTargetType::AllPortalUsers,
            context: self::inboxContext('news', (int) $news->getKey()),
        );
        $this->dispatch($msgBeneficiary, $beneficiaryIds);

        $msgStaff = new NotificationMessage(
            type: InboxNotificationType::NewsStaffCopy,
            title: 'نشر خبر: '.$news->title,
            message: $body,
            senderId: $publisher?->id,
            targetType: NotificationTargetType::Staff,
            context: self::inboxContext('news', (int) $news->getKey()),
        );
        $this->dispatch($msgStaff, $staffIds->all());
    }

    public function registrationApprovedProgram(User $recipient, TrainingProgram $program, ?User $approver = null): void
    {
        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationApproved,
            title: 'تم قبول تسجيلك',
            message: 'تم قبول طلبك في البرنامج التدريبي «'.$program->title.'».',
            senderId: $approver?->id,
            targetType: NotificationTargetType::SingleUser,
            context: self::inboxContext('training_program', (int) $program->getKey()),
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
            context: self::inboxContext('training_program', (int) $program->getKey()),
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
            context: self::inboxContext('learning_path', (int) $path->getKey()),
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
            context: self::inboxContext('learning_path', (int) $path->getKey()),
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
            context: self::inboxContext('volunteer_opportunity', (int) $opportunity->getKey()),
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
            context: self::inboxContext('volunteer_opportunity', (int) $opportunity->getKey()),
        );
        $this->dispatch($msg, [$recipient->id]);
    }

    public function certificateIssued(User $recipient, Certificate $certificate, ?User $issuedBy = null): void
    {
        $certificate->loadMissing('certificateable');
        $certificateable = $certificate->certificateable;
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
            context: self::inboxContext('certificate', (int) $certificate->getKey()),
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
            context: self::inboxContext('volunteer_opportunity', (int) $opportunity->getKey()),
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

    public function learningPathLaunched(LearningPath $path, ?User $publisher = null): void
    {
        $staffIds = collect($this->resolveStaffUserIds());
        $portalIds = collect($this->resolveRecipientIds(NotificationTargetType::AllPortalUsers));
        $beneficiaryIds = $portalIds->diff($staffIds)->values()->all();

        $msg = new NotificationMessage(
            type: InboxNotificationType::LearningPathLaunched,
            title: 'مسار تعليمي جديد: '.$path->title,
            message: 'تم نشر مسار تعليمي جديد. يمكنك الاطلاع على التفاصيل والتسجيل من صفحة المسارات.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::AllPortalUsers,
            context: self::inboxContext('learning_path', (int) $path->getKey()),
        );
        $this->dispatch($msg, $beneficiaryIds);

        $staffMsg = new NotificationMessage(
            type: InboxNotificationType::StaffTrainingEntityCreated,
            title: 'مسار تعليمي جديد: '.$path->title,
            message: 'تم إنشاء أو نشر مسار تعليمي جديد على المنصة.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('learning_path', (int) $path->getKey()),
        );
        $this->dispatch($staffMsg, $this->resolveAdminAndTrainingManagerIds());
    }

    public function volunteerOpportunityFirstPublished(VolunteerOpportunity $opportunity, ?User $publisher = null): void
    {
        $trainees = collect($this->resolveRecipientIds(NotificationTargetType::Trainees));
        $volunteers = collect($this->resolveRecipientIds(NotificationTargetType::Volunteers));
        $staff = collect($this->resolveStaffUserIds());
        $audience = $trainees->merge($volunteers)->diff($staff)->unique()->values()->all();

        $msg = new NotificationMessage(
            type: InboxNotificationType::VolunteerOpportunityPublished,
            title: 'فرصة تطوعية جديدة: '.$opportunity->title,
            message: 'تم نشر فرصة تطوعية جديدة. يمكنك الاطلاع على التفاصيل من قسم التطوع.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('volunteer_opportunity', (int) $opportunity->getKey()),
        );
        $this->dispatch($msg, $audience);

        $staffMsg = new NotificationMessage(
            type: InboxNotificationType::StaffVolunteerOpportunityCreated,
            title: 'فرصة تطوعية جديدة: '.$opportunity->title,
            message: 'تم إنشاء أو نشر فرصة تطوعية على المنصة.',
            senderId: $publisher?->id,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('volunteer_opportunity', (int) $opportunity->getKey()),
        );
        $this->dispatch($staffMsg, $this->resolveStaffForVolunteerDomain());
    }

    public function notifyStaffOfNewProgramRegistration(ProgramRegistration $registration): void
    {
        $registration->loadMissing('trainingProgram', 'user');
        $program = $registration->trainingProgram;
        $user = $registration->user;
        if ($program === null || $user === null) {
            return;
        }

        $title = 'تسجيل جديد في برنامج';
        $message = 'تسجيل جديد في البرنامج «'.$program->title.'» — '.$user->name.'.';

        $msg = new NotificationMessage(
            type: InboxNotificationType::StaffNewProgramRegistration,
            title: $title,
            message: $message,
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('program_registration', (int) $registration->getKey()),
        );
        $this->dispatch($msg, $this->resolveAdminAndTrainingManagerIds());
    }

    public function notifyStaffOfNewPathRegistration(PathRegistration $registration): void
    {
        $registration->loadMissing('learningPath', 'user');
        $path = $registration->learningPath;
        $user = $registration->user;
        if ($path === null || $user === null) {
            return;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::StaffNewPathRegistration,
            title: 'تسجيل جديد في مسار',
            message: 'تسجيل جديد في المسار «'.$path->title.'» — '.$user->name.'.',
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('path_registration', (int) $registration->getKey()),
        );
        $this->dispatch($msg, $this->resolveAdminAndTrainingManagerIds());
    }

    public function notifyStaffOfNewVolunteerRegistration(VolunteerRegistration $registration): void
    {
        $registration->loadMissing('opportunity', 'user');
        $opportunity = $registration->opportunity;
        $user = $registration->user;
        if ($opportunity === null || $user === null) {
            return;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::StaffNewVolunteerRegistration,
            title: 'تسجيل جديد في تطوع',
            message: 'تسجيل جديد في الفرصة «'.$opportunity->title.'» — '.$user->name.'.',
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('volunteer_registration', (int) $registration->getKey()),
        );
        $this->dispatch($msg, $this->resolveStaffForVolunteerDomain());
    }

    public function registrationWindowOpenedForProgram(TrainingProgram $program): void
    {
        $staffIds = collect($this->resolveStaffUserIds());
        $portalIds = collect($this->resolveRecipientIds(NotificationTargetType::AllPortalUsers));
        $beneficiaryIds = $portalIds->diff($staffIds)->values()->all();

        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationWindowOpened,
            title: 'بدء التسجيل: '.$program->title,
            message: 'بدأت فترة التسجيل في البرنامج «'.$program->title.'».',
            senderId: null,
            targetType: NotificationTargetType::AllPortalUsers,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($msg, $beneficiaryIds);

        $staffMsg = new NotificationMessage(
            type: InboxNotificationType::RegistrationWindowOpened,
            title: 'بدء التسجيل (إداري): '.$program->title,
            message: 'بدأت فترة التسجيل في البرنامج «'.$program->title.'».',
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($staffMsg, $this->resolveAdminAndTrainingManagerIds());
    }

    public function registrationWindowClosedForProgram(TrainingProgram $program): void
    {
        $staffIds = collect($this->resolveStaffUserIds());
        $portalIds = collect($this->resolveRecipientIds(NotificationTargetType::AllPortalUsers));
        $beneficiaryIds = $portalIds->diff($staffIds)->values()->all();

        $msg = new NotificationMessage(
            type: InboxNotificationType::RegistrationWindowClosed,
            title: 'انتهاء التسجيل: '.$program->title,
            message: 'انتهت فترة التسجيل في البرنامج «'.$program->title.'».',
            senderId: null,
            targetType: NotificationTargetType::AllPortalUsers,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($msg, $beneficiaryIds);

        $staffMsg = new NotificationMessage(
            type: InboxNotificationType::RegistrationWindowClosed,
            title: 'انتهاء التسجيل (إداري): '.$program->title,
            message: 'انتهت فترة التسجيل في البرنامج «'.$program->title.'».',
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($staffMsg, $this->resolveAdminAndTrainingManagerIds());
    }

    public function trainingRunStartedForProgram(TrainingProgram $program): void
    {
        $staffMsg = new NotificationMessage(
            type: InboxNotificationType::TrainingRunStarted,
            title: 'بدء البرنامج: '.$program->title,
            message: 'بدأت فترة انعقاد البرنامج «'.$program->title.'».',
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($staffMsg, $this->resolveAdminAndTrainingManagerIds());

        $recipientIds = $program->registrations()
            ->where('status', RegistrationStatus::Approved->value)
            ->pluck('user_id')
            ->unique()
            ->all();

        if ($recipientIds === []) {
            return;
        }

        $msg = new NotificationMessage(
            type: InboxNotificationType::BeneficiaryApprovedProgramStarting,
            title: 'بدء البرنامج: '.$program->title,
            message: 'بدأ البرنامج «'.$program->title.'» الذي أنت مسجّل ومقبول فيه.',
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($msg, $recipientIds);
    }

    public function trainingRunEndedForProgram(TrainingProgram $program): void
    {
        $staffMsg = new NotificationMessage(
            type: InboxNotificationType::TrainingRunEnded,
            title: 'انتهاء البرنامج: '.$program->title,
            message: 'انتهت فترة البرنامج «'.$program->title.'».',
            senderId: null,
            targetType: NotificationTargetType::DirectRecipients,
            context: self::inboxContext('training_program', (int) $program->getKey()),
        );
        $this->dispatch($staffMsg, $this->resolveAdminAndTrainingManagerIds());
    }

    /**
     * @return list<int>
     */
    public function resolveStaffUserIds(): array
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->whereIn('role_type', ['staff', 'admin'])
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', [
                        'admin', 'media_pr', 'media_employee', 'pr_employee', 'training_manager', 'volunteering_manager', 'staff',
                    ]));
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function resolveAdminAndTrainingManagerIds(): array
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('role_type', 'admin')
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'training_manager']));
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function resolveStaffForVolunteerDomain(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($r) => $r->whereIn('name', [
                'admin', 'training_manager', 'volunteering_manager',
            ]))
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }
}

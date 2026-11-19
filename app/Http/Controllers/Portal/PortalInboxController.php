<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\ProgramCapacityExceededException;
use App\Filament\Support\InboxNotificationRecordActions;
use App\Http\Controllers\Controller;
use App\Models\InboxNotification;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use App\Services\Inbox\UserNotificationPreferences;
use App\Services\ProgramRegistrationService;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PortalInboxController extends Controller
{
    public function index(Request $request, InboxNotificationService $inbox): View
    {
        $user = $request->user();
        $items = $inbox->latestForUser($user, 100);
        $unreadCount = $inbox->unreadCount($user);

        return view('portal.notifications.index', compact('items', 'unreadCount'));
    }

    public function settings(Request $request): View
    {
        return view('portal.notifications.settings', [
            'user' => $request->user(),
        ]);
    }

    public function updateSettings(Request $request, UserNotificationPreferences $preferences): RedirectResponse
    {
        $request->validate([
            'categories' => ['nullable', 'array'],
        ]);

        $wantsEmail = UserNotificationPreferences::parseBool($request->input('notify_email'));
        $normalized = $preferences->normalizeFromRequest(
            ['categories' => $request->input('categories', [])],
            $wantsEmail,
        );

        $user = $request->user();
        $previousNotifyEmail = (bool) $user->notify_email;

        $user->forceFill([
            'notify_email' => $wantsEmail,
            'notification_settings' => $normalized,
            'notification_prefs_set_at' => now(),
        ])->save();

        if ($previousNotifyEmail !== $wantsEmail) {
            UserActivityLogger::logEmailNotifications($user, $wantsEmail);
        }

        return redirect()
            ->route('portal.notifications.settings')
            ->with('success', 'تم حفظ تفضيلات التنبيهات.');
    }

    public function markRead(Request $request, InboxNotification $notification, InboxNotificationService $inbox): RedirectResponse
    {
        Gate::authorize('update', $notification);

        $inbox->markAsRead($notification, $request->user());

        return back()->with('success', 'تم تعليم التنبيه كمقروء.');
    }

    public function markAllRead(Request $request, InboxNotificationService $inbox): RedirectResponse
    {
        $count = $inbox->markAllAsRead($request->user());
        if ($count === 0) {
            return back()->with('success', 'لا توجد تنبيهات غير مقروءة.');
        }

        return back()->with('success', 'تم تعليم جميع التنبيهات كمقروءة.');
    }

    /**
     * قبول/رفض تسجيل مرتبط بتنبيه (نفس صلاحيات ومنطق لوحة الإدارة).
     */
    public function registrationAction(
        Request $request,
        InboxNotification $notification,
        ProgramRegistrationService $programRegistrationService,
    ): RedirectResponse {
        Gate::authorize('update', $notification);
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'intent' => ['required', 'string', 'in:approve_program,reject_program'],
            'rejected_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $reason = $validated['rejected_reason'] ?? null;

        try {
            return match ($validated['intent']) {
                'approve_program' => $this->portalApproveProgramRegistration($notification, $actor, $programRegistrationService),
                'reject_program' => $this->portalRejectProgramRegistration($notification, $programRegistrationService, $reason),
            };
        } catch (ProgramCapacityExceededException) {
            return back()->with('error', 'البرنامج بلغ طاقته القصوى.');
        }
    }

    private function portalApproveProgramRegistration(
        InboxNotification $notification,
        User $actor,
        ProgramRegistrationService $programRegistrationService,
    ): RedirectResponse {
        abort_unless(InboxNotificationRecordActions::canApproveProgramRegistration($notification), 403);
        $reg = InboxNotificationRecordActions::programRegistration($notification);
        if ($reg === null) {
            abort(404);
        }
        Gate::authorize('approve', $reg);
        $programRegistrationService->approve($reg, $actor);
        $notification->markAsRead();

        return back()->with('success', 'تم قبول التسجيل في البرنامج.');
    }

    private function portalRejectProgramRegistration(
        InboxNotification $notification,
        ProgramRegistrationService $programRegistrationService,
        ?string $reason,
    ): RedirectResponse {
        abort_unless(InboxNotificationRecordActions::canRejectProgramRegistration($notification), 403);
        $reg = InboxNotificationRecordActions::programRegistration($notification);
        if ($reg === null) {
            abort(404);
        }
        Gate::authorize('reject', $reg);
        $programRegistrationService->reject($reg, $reason);
        $notification->markAsRead();

        return back()->with('success', 'تم رفض التسجيل في البرنامج.');
    }
}

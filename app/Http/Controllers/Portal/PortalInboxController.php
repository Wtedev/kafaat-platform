<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\OpportunityCapacityExceededException;
use App\Exceptions\PathCapacityExceededException;
use App\Exceptions\ProgramCapacityExceededException;
use App\Filament\Support\InboxNotificationRecordActions;
use App\Http\Controllers\Controller;
use App\Models\InboxNotification;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use App\Services\PathRegistrationService;
use App\Services\ProgramRegistrationService;
use App\Services\VolunteerRegistrationService;
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
        PathRegistrationService $pathRegistrationService,
        VolunteerRegistrationService $volunteerRegistrationService,
    ): RedirectResponse {
        Gate::authorize('update', $notification);
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'intent' => ['required', 'string', 'in:approve_program,reject_program,approve_path,reject_path,approve_volunteer,reject_volunteer'],
            'rejected_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $reason = $validated['rejected_reason'] ?? null;

        try {
            return match ($validated['intent']) {
                'approve_program' => $this->portalApproveProgramRegistration($notification, $actor, $programRegistrationService),
                'reject_program' => $this->portalRejectProgramRegistration($notification, $programRegistrationService, $reason),
                'approve_path' => $this->portalApprovePathRegistration($notification, $actor, $pathRegistrationService),
                'reject_path' => $this->portalRejectPathRegistration($notification, $pathRegistrationService, $reason),
                'approve_volunteer' => $this->portalApproveVolunteerRegistration($notification, $actor, $volunteerRegistrationService),
                'reject_volunteer' => $this->portalRejectVolunteerRegistration($notification, $actor, $volunteerRegistrationService, $reason),
            };
        } catch (ProgramCapacityExceededException) {
            return back()->with('error', 'البرنامج بلغ طاقته القصوى.');
        } catch (PathCapacityExceededException) {
            return back()->with('error', 'المسار بلغ طاقته القصوى.');
        } catch (OpportunityCapacityExceededException) {
            return back()->with('error', 'الفرصة التطوعية بلغت طاقتها القصوى.');
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

    private function portalApprovePathRegistration(
        InboxNotification $notification,
        User $actor,
        PathRegistrationService $pathRegistrationService,
    ): RedirectResponse {
        abort_unless(InboxNotificationRecordActions::canApprovePathRegistration($notification), 403);
        $reg = InboxNotificationRecordActions::pathRegistration($notification);
        if ($reg === null) {
            abort(404);
        }
        Gate::authorize('approve', $reg);
        $pathRegistrationService->approve($reg, $actor);
        $notification->markAsRead();

        return back()->with('success', 'تم قبول التسجيل في المسار.');
    }

    private function portalRejectPathRegistration(
        InboxNotification $notification,
        PathRegistrationService $pathRegistrationService,
        ?string $reason,
    ): RedirectResponse {
        abort_unless(InboxNotificationRecordActions::canRejectPathRegistration($notification), 403);
        $reg = InboxNotificationRecordActions::pathRegistration($notification);
        if ($reg === null) {
            abort(404);
        }
        Gate::authorize('reject', $reg);
        $pathRegistrationService->reject($reg, $reason);
        $notification->markAsRead();

        return back()->with('success', 'تم رفض التسجيل في المسار.');
    }

    private function portalApproveVolunteerRegistration(
        InboxNotification $notification,
        User $actor,
        VolunteerRegistrationService $volunteerRegistrationService,
    ): RedirectResponse {
        abort_unless(InboxNotificationRecordActions::canApproveVolunteerRegistration($notification), 403);
        $reg = InboxNotificationRecordActions::volunteerRegistration($notification);
        if ($reg === null) {
            abort(404);
        }
        Gate::authorize('approve', $reg);
        $volunteerRegistrationService->approve($reg, $actor);
        $notification->markAsRead();

        return back()->with('success', 'تم قبول التسجيل التطوعي.');
    }

    private function portalRejectVolunteerRegistration(
        InboxNotification $notification,
        User $actor,
        VolunteerRegistrationService $volunteerRegistrationService,
        ?string $reason,
    ): RedirectResponse {
        abort_unless(InboxNotificationRecordActions::canRejectVolunteerRegistration($notification), 403);
        $reg = InboxNotificationRecordActions::volunteerRegistration($notification);
        if ($reg === null) {
            abort(404);
        }
        Gate::authorize('reject', $reg);
        $volunteerRegistrationService->reject($reg, $actor, $reason);
        $notification->markAsRead();

        return back()->with('success', 'تم رفض التسجيل التطوعي.');
    }
}

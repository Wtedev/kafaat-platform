<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Services\Inbox\InboxNotificationService;
use App\Services\Portal\PortalDashboardComposer;
use Illuminate\Http\Request;

class PortalDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load('profile');

        $programsRegistered = $user->programRegistrations()
            ->whereHas('trainingProgram', fn ($query) => $query->published())
            ->count();
        $programsCompleted = $user->programRegistrations()
            ->whereHas('trainingProgram', fn ($query) => $query->published())
            ->where('status', RegistrationStatus::Completed)
            ->count();
        $certificatesCount = $user->certificates()->count();

        $composed = PortalDashboardComposer::compose($user);
        $activities = $composed['activities'];

        $inbox = app(InboxNotificationService::class);
        $inboxPreview = $inbox->latestForUser($user, 6);
        $inboxUnreadCount = $inbox->unreadCount($user);

        return view('portal.dashboard', compact(
            'user',
            'programsRegistered',
            'programsCompleted',
            'certificatesCount',
            'activities',
            'inboxPreview',
            'inboxUnreadCount',
        ));
    }
}

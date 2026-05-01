<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Services\Portal\PortalDashboardComposer;
use Illuminate\Http\Request;

class PortalDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load('profile');

        $programsRegistered = $user->programRegistrations()->count()
            + $user->learningPathRegistrations()->count();
        $programsCompleted = $user->programRegistrations()
            ->where('status', RegistrationStatus::Completed)
            ->count()
            + $user->learningPathRegistrations()
                ->where('status', RegistrationStatus::Completed)
                ->count();
        $approvedHours = $user->totalApprovedVolunteerHours();
        $certificatesCount = $user->certificates()->count();

        $composed = PortalDashboardComposer::compose($user);
        $activities = $composed['activities'];
        $volunteerRows = $composed['volunteerRows'];

        return view('portal.dashboard', compact(
            'user',
            'programsRegistered',
            'programsCompleted',
            'approvedHours',
            'certificatesCount',
            'activities',
            'volunteerRows',
        ));
    }
}

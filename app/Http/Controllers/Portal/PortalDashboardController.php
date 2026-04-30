<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Enums\VolunteerHoursStatus;
use Illuminate\Http\Request;

class PortalDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $pathCount = $user->learningPathRegistrations()->count();
        $programCount = $user->programRegistrations()->count();
        $volunteerCount = $user->volunteerRegistrations()->count();
        $approvedHours = $user->totalApprovedVolunteerHours();

        $certificates = $user->certificates()
            ->latest('issued_at')
            ->take(5)
            ->get();

        $recentPathRegs = $user->learningPathRegistrations()
            ->with('learningPath')
            ->latest()
            ->take(5)
            ->get();

        $recentProgramRegs = $user->programRegistrations()
            ->with('trainingProgram')
            ->latest()
            ->take(5)
            ->get();

        return view('portal.dashboard', compact(
            'pathCount',
            'programCount',
            'volunteerCount',
            'approvedHours',
            'certificates',
            'recentPathRegs',
            'recentProgramRegs',
        ));
    }
}

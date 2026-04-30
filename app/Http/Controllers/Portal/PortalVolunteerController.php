<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PortalVolunteerController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $registrations = $user->volunteerRegistrations()
            ->with('opportunity')
            ->latest()
            ->paginate(15);

        // Attach approved hours for each registration
        foreach ($registrations as $registration) {
            $registration->approved_hours = $registration->getApprovedHours();
        }

        return view('portal.volunteering', compact('registrations'));
    }
}

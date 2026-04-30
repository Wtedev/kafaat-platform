<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PortalProgramController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $registrations = $user->programRegistrations()
            ->with(['trainingProgram'])
            ->latest()
            ->paginate(15);

        // Attach certificate (if issued) for each registration
        foreach ($registrations as $registration) {
            $program = $registration->trainingProgram;
            if ($program) {
                $registration->certificate = $user->certificates()
                    ->where('certificateable_type', get_class($program))
                    ->where('certificateable_id', $program->id)
                    ->first();
            } else {
                $registration->certificate = null;
            }
        }

        return view('portal.programs', compact('registrations'));
    }
}

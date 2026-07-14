<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\AttendanceLiveSessionService;
use App\Support\ProgramRegistrationSuccessPresenter;
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

        $user->loadMissing('profile');
        $liveSessionService = app(AttendanceLiveSessionService::class);

        foreach ($registrations as $registration) {
            $program = $registration->trainingProgram;
            if ($program) {
                $registration->certificate = $user->certificates()
                    ->where('certificateable_type', get_class($program))
                    ->where('certificateable_id', $program->id)
                    ->first();
                $registration->attendance_pass = ProgramRegistrationSuccessPresenter::present(
                    $program,
                    $registration,
                    $user,
                );
                $registration->live_session = $liveSessionService->activeSessionFor($program);
            } else {
                $registration->certificate = null;
                $registration->attendance_pass = null;
                $registration->live_session = null;
            }
        }

        return view('portal.programs', [
            'registrations' => $registrations,
            'openAttendanceProgramId' => $request->integer('open_attendance') ?: null,
        ]);
    }
}

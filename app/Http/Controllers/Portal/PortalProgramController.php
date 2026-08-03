<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ProgramPrepDayType;
use App\Http\Controllers\Controller;
use App\Services\AttendanceLiveSessionService;
use App\Services\ProgramAttendanceService;
use App\Support\ProgramRegistrationSuccessPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PortalProgramController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $registrations = $user->programRegistrations()
            ->with(['trainingProgram.prepDays'])
            ->latest()
            ->paginate(15);

        $user->loadMissing('profile');
        $liveSessionService = app(AttendanceLiveSessionService::class);
        $attendanceService = app(ProgramAttendanceService::class);
        $today = Carbon::today(config('app.timezone'))->toDateString();

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
                $todayPrep = $attendanceService->todayPrepDay($program);
                $registration->today_prep_day = $todayPrep;
                $registration->today_prep_type = $todayPrep?->delivery_type;
                $registration->live_session = $todayPrep?->delivery_type === ProgramPrepDayType::Remote
                    ? $liveSessionService->activeSessionFor($program, $today)
                    : null;
            } else {
                $registration->certificate = null;
                $registration->attendance_pass = null;
                $registration->live_session = null;
                $registration->today_prep_day = null;
                $registration->today_prep_type = null;
            }
        }

        return view('portal.programs', [
            'registrations' => $registrations,
            'openAttendanceProgramId' => $request->integer('open_attendance') ?: null,
        ]);
    }
}

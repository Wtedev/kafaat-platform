<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramPrepDayType;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLiveSession;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TrainingProgram;
use App\Services\AttendanceLiveSessionService;
use App\Services\Certificates\CertificateEligibilityService;
use App\Services\Certificates\CertificateProgressCopy;
use App\Services\ProgramAttendanceService;
use App\Support\ProgramRegistrationSuccessPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PortalProgramController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $registrations = $user->programRegistrations()
            ->with([
                'trainingProgram.prepDays',
                'attendanceRecords' => fn ($q) => $q->whereDate('training_date', $today),
            ])
            ->latest()
            ->paginate(15);

        $user->loadMissing('profile');
        $liveSessionService = app(AttendanceLiveSessionService::class);
        $attendanceService = app(ProgramAttendanceService::class);
        $page = $registrations->getCollection();
        $programIds = $page->pluck('training_program_id')->filter()->unique()->values();
        $ownerType = (new TrainingProgram)->getMorphClass();
        $certificatesByProgram = Certificate::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('certificateable_type', $ownerType)
            ->whereIn('certificateable_id', $programIds)
            ->get()
            ->keyBy(fn (Certificate $certificate): int => (int) $certificate->certificateable_id);
        $attendancePercentages = $attendanceService->percentagesForRegistrations($page);
        $eligibility = app(CertificateEligibilityService::class)->evaluateMany($page);
        $templates = CertificateTemplate::query()
            ->where('owner_type', $ownerType)
            ->whereIn('owner_id', $programIds)
            ->get()
            ->keyBy(fn (CertificateTemplate $template): int => (int) $template->owner_id);
        $progressCopy = app(CertificateProgressCopy::class);
        $certificateProgress = [];

        foreach ($registrations as $registration) {
            $program = $registration->trainingProgram;
            if ($program) {
                $registration->certificate = $certificatesByProgram->get((int) $program->id);
                $accepted = in_array($registration->status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true);
                if ($accepted) {
                    $template = $templates->get((int) $program->id);
                    $attendance = $attendancePercentages[(int) $registration->getKey()] ?? $attendancePercentages[$registration->getKey()] ?? null;
                    $score = $registration->score !== null ? (float) $registration->score : null;
                    $certificateProgress[(int) $registration->getKey()] = $template
                        ? $progressCopy->line($template->eligibility, $attendance, $score)
                        : ($eligibility->get($registration->getKey())?->reasons[0] ?? 'لم يُضبط قالب الشهادة بعد');
                }
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
                $todayRecord = $registration->attendanceRecords->first(
                    fn ($row): bool => $row->status === AttendanceStatus::Present,
                );
                $registration->today_present = $todayRecord !== null;
                $registration->today_marked_at = $todayRecord?->created_at;
                $registration->live_session_ended_today = false;
                if ($todayPrep?->delivery_type === ProgramPrepDayType::Remote && $registration->live_session === null) {
                    $latest = AttendanceLiveSession::query()
                        ->where('attendable_type', $program->getMorphClass())
                        ->where('attendable_id', $program->id)
                        ->whereDate('session_date', $today)
                        ->latest('started_at')
                        ->first();
                    $registration->live_session_ended_today = $latest !== null && ! $latest->isActive();
                }
            } else {
                $registration->certificate = null;
                $registration->attendance_pass = null;
                $registration->live_session = null;
                $registration->today_prep_day = null;
                $registration->today_prep_type = null;
                $registration->today_present = false;
                $registration->today_marked_at = null;
                $registration->live_session_ended_today = false;
            }
        }

        return view('portal.programs', [
            'registrations' => $registrations,
            'openAttendanceProgramId' => $request->integer('open_attendance') ?: null,
            'attendancePercentages' => $attendancePercentages,
            'certificateProgress' => $certificateProgress,
        ]);
    }
}

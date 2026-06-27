<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\AttendanceLiveSessionService;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalAttendanceCheckInController extends Controller
{
    public function checkInProgram(Request $request, TrainingProgram $trainingProgram): RedirectResponse
    {
        $user = $request->user();

        $registration = ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->where('training_program_id', $trainingProgram->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->firstOrFail();

        $session = app(AttendanceLiveSessionService::class)->activeSessionFor($trainingProgram);

        if ($session === null) {
            return back()->with('attendance_error', 'لا توجد جلسة حضور مفتوحة حالياً. انتظر حتى يفتح المنسق جلسة جديدة.');
        }

        app(AttendanceLiveSessionService::class)->checkInProgram($session, $registration);

        UserActivityLogger::logAttendanceCheckIn(
            $user,
            'برنامج: «'.($trainingProgram->title ?? 'برنامج تدريبي').'»',
        );

        return back()->with('attendance_success', 'تم تسجيل حضورك لهذا اليوم بنجاح.');
    }

    public function checkInPath(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $user = $request->user();

        $registration = PathRegistration::query()
            ->where('user_id', $user->id)
            ->where('learning_path_id', $learningPath->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->firstOrFail();

        $session = app(AttendanceLiveSessionService::class)->activeSessionFor($learningPath);

        if ($session === null) {
            return back()->with('attendance_error', 'لا توجد جلسة حضور مفتوحة حالياً. انتظر حتى يفتح المنسق جلسة جديدة.');
        }

        app(AttendanceLiveSessionService::class)->checkInPath($session, $registration);

        UserActivityLogger::logAttendanceCheckIn(
            $user,
            'مسار: «'.($learningPath->title ?? 'مسار تدريبي').'»',
        );

        return back()->with('attendance_success', 'تم تسجيل حضورك لهذا اليوم بنجاح.');
    }
}

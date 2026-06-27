<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceLiveSession;
use App\Models\LearningPath;
use App\Models\PathAttendance;
use App\Models\PathRegistration;
use App\Models\ProgramAttendance;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceLiveSessionService
{
    public const SESSION_MINUTES = 5;

    public function activeSessionFor(Model $attendable): ?AttendanceLiveSession
    {
        if (! Schema::hasTable('attendance_live_sessions')) {
            return null;
        }

        return AttendanceLiveSession::query()
            ->where('attendable_type', $attendable->getMorphClass())
            ->where('attendable_id', $attendable->getKey())
            ->where('expires_at', '>', now())
            ->latest('started_at')
            ->first();
    }

    public function startSession(Model $attendable, User $admin): AttendanceLiveSession
    {
        if (! Schema::hasTable('attendance_live_sessions')) {
            throw ValidationException::withMessages([
                'session' => 'جدول جلسات الحضور غير متوفر. شغّل ترحيلات قاعدة البيانات: php artisan migrate',
            ]);
        }

        AttendanceLiveSession::query()
            ->where('attendable_type', $attendable->getMorphClass())
            ->where('attendable_id', $attendable->getKey())
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        return AttendanceLiveSession::create([
            'attendable_type' => $attendable->getMorphClass(),
            'attendable_id' => $attendable->getKey(),
            'created_by' => $admin->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(self::SESSION_MINUTES),
        ]);
    }

    public function checkInProgram(AttendanceLiveSession $session, ProgramRegistration $registration): void
    {
        $this->assertSessionActiveFor($session, $registration->trainingProgram);
        $this->assertRegistrationApproved($registration);

        ProgramAttendance::updateOrCreate(
            [
                'program_registration_id' => $registration->id,
                'training_date' => today()->toDateString(),
            ],
            [
                'status' => AttendanceStatus::Present,
                'notes' => 'تسجيل حضور ذاتي',
            ],
        );
    }

    public function checkInPath(AttendanceLiveSession $session, PathRegistration $registration): void
    {
        $this->assertSessionActiveFor($session, $registration->learningPath);
        $this->assertPathRegistrationApproved($registration);

        PathAttendance::updateOrCreate(
            [
                'path_registration_id' => $registration->id,
                'attendance_date' => today()->toDateString(),
            ],
            [
                'status' => AttendanceStatus::Present,
                'notes' => 'تسجيل حضور ذاتي',
            ],
        );
    }

    private function assertSessionActiveFor(AttendanceLiveSession $session, Model $attendable): void
    {
        if (
            $session->attendable_type !== $attendable->getMorphClass()
            || (int) $session->attendable_id !== (int) $attendable->getKey()
        ) {
            throw ValidationException::withMessages([
                'session' => 'جلسة الحضور غير صالحة لهذا النشاط.',
            ]);
        }

        if (! $session->isActive()) {
            throw ValidationException::withMessages([
                'session' => 'انتهت مدة جلسة الحضور. اطلب من المنسق فتح جلسة جديدة.',
            ]);
        }
    }

    private function assertRegistrationApproved(ProgramRegistration $registration): void
    {
        if (! $registration->isApproved() && ! $registration->isCompleted()) {
            throw ValidationException::withMessages([
                'registration' => 'التسجيل غير مقبول.',
            ]);
        }
    }

    private function assertPathRegistrationApproved(PathRegistration $registration): void
    {
        if (! $registration->isApproved() && ! $registration->isCompleted()) {
            throw ValidationException::withMessages([
                'registration' => 'التسجيل غير مقبول.',
            ]);
        }
    }
}

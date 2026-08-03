<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramPrepDayType;
use App\Models\AttendanceLiveSession;
use App\Models\PathAttendance;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceLiveSessionService
{
    public const SESSION_MINUTES = 5;

    public function activeSessionFor(Model $attendable, ?string $sessionDate = null): ?AttendanceLiveSession
    {
        if (! Schema::hasTable('attendance_live_sessions')) {
            return null;
        }

        $query = AttendanceLiveSession::query()
            ->where('attendable_type', $attendable->getMorphClass())
            ->where('attendable_id', $attendable->getKey())
            ->where('expires_at', '>', now());

        if ($sessionDate !== null && Schema::hasColumn('attendance_live_sessions', 'session_date')) {
            $query->whereDate('session_date', $sessionDate);
        }

        return $query->latest('started_at')->first();
    }

    /**
     * Start a 5-minute remote attendance window for TODAY's remote prep day.
     * If an active session already exists for today, returns it without replacing.
     */
    public function startProgramRemoteSession(TrainingProgram $program, User $admin): AttendanceLiveSession
    {
        if (! Schema::hasTable('attendance_live_sessions')) {
            throw ValidationException::withMessages([
                'session' => 'جدول جلسات الحضور غير متوفر. شغّل ترحيلات قاعدة البيانات: php artisan migrate',
            ]);
        }

        $today = Carbon::today(config('app.timezone'))->toDateString();
        $prepDay = app(ProgramAttendanceService::class)->todayPrepDay($program);

        if ($prepDay === null || $prepDay->delivery_type !== ProgramPrepDayType::Remote) {
            throw ValidationException::withMessages([
                'session' => 'فتح التحضير متاح فقط عندما يكون يوم اليوم من أيام البرنامج عن بُعد.',
            ]);
        }

        $existing = $this->activeSessionFor($program, $today);
        if ($existing !== null) {
            return $existing;
        }

        return AttendanceLiveSession::create([
            'attendable_type' => $program->getMorphClass(),
            'attendable_id' => $program->getKey(),
            'program_prep_day_id' => $prepDay->id,
            'session_date' => $today,
            'created_by' => $admin->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(self::SESSION_MINUTES),
        ]);
    }

    /**
     * Path / generic sessions: do not silently replace an active window.
     */
    public function startSession(Model $attendable, User $admin): AttendanceLiveSession
    {
        if ($attendable instanceof TrainingProgram) {
            return $this->startProgramRemoteSession($attendable, $admin);
        }

        if (! Schema::hasTable('attendance_live_sessions')) {
            throw ValidationException::withMessages([
                'session' => 'جدول جلسات الحضور غير متوفر. شغّل ترحيلات قاعدة البيانات: php artisan migrate',
            ]);
        }

        $existing = $this->activeSessionFor($attendable);
        if ($existing !== null) {
            return $existing;
        }

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
        $registration->loadMissing('trainingProgram');
        $this->assertSessionActiveFor($session, $registration->trainingProgram);
        $this->assertRegistrationApproved($registration);

        $prepDate = $session->session_date instanceof Carbon
            ? $session->session_date->toDateString()
            : ($session->session_date !== null
                ? (string) $session->session_date
                : Carbon::today(config('app.timezone'))->toDateString());

        app(ProgramAttendanceService::class)->markPresentFromLiveSession(
            $registration,
            $prepDate,
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

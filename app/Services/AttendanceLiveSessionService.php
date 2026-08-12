<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramPrepDayType;
use App\Enums\RegistrationStatus;
use App\Models\AttendanceLiveSession;
use App\Models\PathAttendance;
use App\Models\PathRegistration;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->where('expires_at', '>', now())
            ->whereNull('closed_at');

        if ($sessionDate !== null && Schema::hasColumn('attendance_live_sessions', 'session_date')) {
            $query->whereDate('session_date', $sessionDate);
        }

        return $query->latest('started_at')->first();
    }

    /**
     * Start a 5-minute remote attendance window for TODAY's remote prep day.
     * If an active session already exists for today, returns it without extending.
     */
    public function startProgramRemoteSession(
        TrainingProgram $program,
        User|ProgramAttendanceChecker $opener,
    ): AttendanceLiveSession {
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

        return DB::transaction(function () use ($program, $opener, $today, $prepDay): AttendanceLiveSession {
            $existing = AttendanceLiveSession::query()
                ->where('attendable_type', $program->getMorphClass())
                ->where('attendable_id', $program->getKey())
                ->whereDate('session_date', $today)
                ->where('expires_at', '>', now())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->latest('started_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $payload = [
                'attendable_type' => $program->getMorphClass(),
                'attendable_id' => $program->getKey(),
                'program_prep_day_id' => $prepDay->id,
                'session_date' => $today,
                'started_at' => now(),
                'expires_at' => now()->addMinutes(self::SESSION_MINUTES),
                'closed_at' => null,
            ];

            if ($opener instanceof User) {
                $payload['created_by'] = $opener->id;
                $payload['opened_by_checker_id'] = null;
            } else {
                $payload['created_by'] = null;
                $payload['opened_by_checker_id'] = $opener->id;
            }

            return AttendanceLiveSession::query()->create($payload);
        });
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

        return DB::transaction(function () use ($attendable, $admin): AttendanceLiveSession {
            $existing = AttendanceLiveSession::query()
                ->where('attendable_type', $attendable->getMorphClass())
                ->where('attendable_id', $attendable->getKey())
                ->where('expires_at', '>', now())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->latest('started_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return AttendanceLiveSession::query()->create([
                'attendable_type' => $attendable->getMorphClass(),
                'attendable_id' => $attendable->getKey(),
                'created_by' => $admin->id,
                'started_at' => now(),
                'expires_at' => now()->addMinutes(self::SESSION_MINUTES),
            ]);
        });
    }

    /**
     * End an active session early. Idempotent if already inactive.
     */
    public function endSession(AttendanceLiveSession $session): AttendanceLiveSession
    {
        if (! $session->isActive()) {
            return $session->fresh() ?? $session;
        }

        $session->forceFill([
            'closed_at' => now(),
        ])->save();

        return $session->fresh() ?? $session;
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

    /**
     * Live status payload for the prep-officer gate UI.
     *
     * @return array{
     *     can_open: bool,
     *     active: bool,
     *     ended: bool,
     *     session_minutes: int,
     *     remaining_seconds: int,
     *     expires_at_ms: int|null,
     *     started_at: string|null,
     *     expires_at: string|null,
     *     closed_at: string|null,
     *     present_count: int,
     *     approved_count: int,
     *     attendees: list<array{name: string, present: bool, marked_at: string|null}>
     * }
     */
    public function gateStatusPayload(TrainingProgram $program, ?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::now(config('app.timezone')))->timezone(config('app.timezone'));
        $today = $asOf->toDateString();
        $prepDay = app(ProgramAttendanceService::class)->todayPrepDay($program, $asOf);
        $canOpen = $prepDay !== null && $prepDay->delivery_type === ProgramPrepDayType::Remote;

        $session = $this->activeSessionFor($program, $today);
        $latestToday = AttendanceLiveSession::query()
            ->where('attendable_type', $program->getMorphClass())
            ->where('attendable_id', $program->getKey())
            ->whereDate('session_date', $today)
            ->latest('started_at')
            ->first();

        $active = $session !== null && $session->isActive();
        $ended = ! $active && $latestToday !== null && (
            $latestToday->closed_at !== null
            || ($latestToday->expires_at !== null && $latestToday->expires_at->lessThanOrEqualTo(now()))
        );

        $approved = ProgramRegistration::query()
            ->with([
                'user',
                'attendanceRecords' => fn ($q) => $q->whereDate('training_date', $today),
            ])
            ->where('training_program_id', $program->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->orderBy('id')
            ->get();

        $attendees = [];
        $presentCount = 0;

        foreach ($approved as $registration) {
            /** @var ProgramAttendance|null $record */
            $record = $registration->attendanceRecords->first(
                fn (ProgramAttendance $row): bool => $row->status === AttendanceStatus::Present,
            );
            $present = $record !== null;
            if ($present) {
                $presentCount++;
            }

            $attendees[] = [
                'name' => $registration->user?->fullName()
                    ?: ($registration->user?->name ?? 'مستفيد'),
                'present' => $present,
                'marked_at' => $present && $record?->created_at
                    ? ar_date_time($record->created_at->timezone(config('app.timezone')))
                    : null,
            ];
        }

        usort($attendees, function (array $a, array $b): int {
            if ($a['present'] === $b['present']) {
                return strcmp($a['name'], $b['name']);
            }

            return $a['present'] ? -1 : 1;
        });

        $display = $active ? $session : ($ended ? $latestToday : null);

        return [
            'can_open' => $canOpen,
            'active' => $active,
            'ended' => $ended,
            'session_minutes' => self::SESSION_MINUTES,
            'remaining_seconds' => $active ? $session->remainingSeconds() : 0,
            'expires_at_ms' => $active ? $session->expires_at->getTimestamp() * 1000 : null,
            'started_at' => $display?->started_at
                ? ar_date_time($display->started_at->timezone(config('app.timezone')))
                : null,
            'expires_at' => $display?->expires_at
                ? ar_date_time($display->expires_at->timezone(config('app.timezone')))
                : null,
            'closed_at' => $display?->closed_at
                ? ar_date_time($display->closed_at->timezone(config('app.timezone')))
                : null,
            'present_count' => $presentCount,
            'approved_count' => $approved->count(),
            'attendees' => $attendees,
        ];
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

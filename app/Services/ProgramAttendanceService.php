<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\AuditLogResult;
use App\Enums\RegistrationStatus;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProgramAttendanceService
{
    /**
     * Arabic day names keyed by Carbon dayOfWeek (0 = Sunday … 6 = Saturday).
     */
    private const DAY_NAMES = [
        0 => 'الأحد',
        1 => 'الاثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Prep days that require attendance, chronological Y-m-d strings.
     *
     * @return list<string>
     */
    public function attendancePrepDateStrings(TrainingProgram $program): array
    {
        return $this->attendancePrepDays($program)
            ->map(fn (ProgramPrepDay $day): string => $day->dateString())
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, ProgramPrepDay>
     */
    public function attendancePrepDays(TrainingProgram $program): Collection
    {
        if ($program->relationLoaded('prepDays')) {
            return $program->prepDays
                ->filter(fn (ProgramPrepDay $day): bool => (bool) $day->requires_attendance)
                ->sortBy(fn (ProgramPrepDay $day): string => $day->dateString())
                ->values();
        }

        return $program->attendancePrepDays()->chronological()->get();
    }

    /**
     * Due prep days for % denominator: requires attendance and date <= today (Asia/Riyadh).
     *
     * @return list<string>
     */
    public function dueAttendancePrepDateStrings(TrainingProgram $program, ?Carbon $asOf = null): array
    {
        $today = ($asOf ?? Carbon::today(config('app.timezone')))->toDateString();

        return array_values(array_filter(
            $this->attendancePrepDateStrings($program),
            static fn (string $date): bool => $date <= $today,
        ));
    }

    /**
     * Default date for daily prep UI: today if a prep day, else nearest prep day.
     */
    public function defaultPrepDate(TrainingProgram $program, ?Carbon $asOf = null): ?string
    {
        $dates = $this->attendancePrepDateStrings($program);

        if ($dates === []) {
            return null;
        }

        $today = ($asOf ?? Carbon::today(config('app.timezone')))->startOfDay();

        if (in_array($today->toDateString(), $dates, true)) {
            return $today->toDateString();
        }

        $nearest = null;
        $nearestAbs = null;

        foreach ($dates as $date) {
            $candidate = Carbon::parse($date, config('app.timezone'))->startOfDay();
            $signed = $today->diffInDays($candidate, false);
            $abs = abs($signed);

            if (
                $nearestAbs === null
                || $abs < $nearestAbs
                || ($abs === $nearestAbs && $signed >= 0)
            ) {
                $nearest = $date;
                $nearestAbs = $abs;
            }
        }

        return $nearest;
    }

    /**
     * @return array<string, string> date => label
     */
    public function attendancePrepDateOptions(TrainingProgram $program): array
    {
        $options = [];

        foreach ($this->attendancePrepDays($program) as $day) {
            $options[$day->dateString()] = $day->displayLabel();
        }

        return $options;
    }

    public function isValidAttendancePrepDate(TrainingProgram $program, string $date): bool
    {
        return in_array($date, $this->attendancePrepDateStrings($program), true);
    }

    /**
     * B1: do not pre-create absent rows. Kept for API compatibility; always returns 0.
     */
    public function generateSessions(ProgramRegistration $registration): int
    {
        return 0;
    }

    /**
     * B1: do not pre-create absent rows. Kept for API compatibility; always returns 0.
     */
    public function generateSessionsForAllRegistrations(TrainingProgram $program): int
    {
        return 0;
    }

    /**
     * Attendance % = (present + late) / due prep days up to today.
     * Returns null when no due prep days yet (UI shows «—»).
     */
    public function calculatePercentage(ProgramRegistration $registration, ?Carbon $asOf = null): ?float
    {
        $registration->loadMissing('trainingProgram');
        $dueDays = $this->dueAttendancePrepDateStrings($registration->trainingProgram, $asOf);

        if ($dueDays === []) {
            return null;
        }

        $attended = $registration->attendanceRecords()
            ->whereIn('status', AttendanceStatus::attendedValues())
            ->get()
            ->filter(fn (ProgramAttendance $row): bool => in_array(
                $row->training_date->toDateString(),
                $dueDays,
                true,
            ))
            ->count();

        return round($attended / count($dueDays) * 100, 2);
    }

    public function countExpectedTrainingDays(TrainingProgram $program): int
    {
        return count($this->attendancePrepDateStrings($program));
    }

    public function countAttendedDays(ProgramRegistration $registration): int
    {
        $dates = $this->attendancePrepDateStrings($registration->trainingProgram);

        if ($dates === []) {
            return 0;
        }

        return $registration->attendanceRecords()
            ->whereIn('status', AttendanceStatus::attendedValues())
            ->get()
            ->filter(fn (ProgramAttendance $row): bool => in_array(
                $row->training_date->toDateString(),
                $dates,
                true,
            ))
            ->count();
    }

    /**
     * @return array{total: int, present: int, late: int, absent: int, excused: int, unspecified: int}
     */
    public function getSummary(ProgramRegistration $registration): array
    {
        $dates = $this->attendancePrepDateStrings($registration->trainingProgram);
        $total = count($dates);

        if ($total === 0) {
            return [
                'total' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'excused' => 0,
                'unspecified' => 0,
            ];
        }

        $records = $registration->attendanceRecords()
            ->get()
            ->filter(fn (ProgramAttendance $row): bool => in_array(
                $row->training_date->toDateString(),
                $dates,
                true,
            ))
            ->keyBy(fn (ProgramAttendance $row): string => $row->training_date->toDateString());

        $present = 0;
        $late = 0;
        $absent = 0;
        $excused = 0;
        $specified = 0;

        foreach ($dates as $date) {
            $record = $records->get($date);
            if ($record === null) {
                continue;
            }

            $specified++;
            match ($record->status) {
                AttendanceStatus::Present => $present++,
                AttendanceStatus::Late => $late++,
                AttendanceStatus::Absent => $absent++,
                AttendanceStatus::Excused => $excused++,
                default => null,
            };
        }

        return [
            'total' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $excused,
            'unspecified' => $total - $specified,
        ];
    }

    public function dayName(int $dayOfWeek): string
    {
        return self::DAY_NAMES[$dayOfWeek] ?? (string) $dayOfWeek;
    }

    public function statusForDate(ProgramRegistration $registration, string $date): ?AttendanceStatus
    {
        $record = $registration->relationLoaded('attendanceRecords')
            ? $registration->attendanceRecords->first(
                fn (ProgramAttendance $row): bool => $row->training_date->toDateString() === $date,
            )
            : $registration->attendanceRecords()
                ->whereDate('training_date', $date)
                ->first();

        return $record?->status;
    }

    public function markManualDay(
        ProgramRegistration $registration,
        string $date,
        AttendanceStatus $status,
        ?string $notes = null,
        ?User $actor = null,
    ): ProgramAttendance {
        $registration->loadMissing('trainingProgram');
        $program = $registration->trainingProgram;

        if ($program === null || ! $this->isValidAttendancePrepDate($program, $date)) {
            throw ValidationException::withMessages([
                'training_date' => 'اليوم المحدد ليس من أيام التحضير لهذا البرنامج.',
            ]);
        }

        return $this->upsertStatus($registration, $date, $status, $notes, $actor, 'manual');
    }

    /**
     * Reset (delete) attendance row for a day → conceptual «غير محدد».
     */
    public function clearDay(
        ProgramRegistration $registration,
        string $date,
        ?User $actor = null,
    ): void {
        $existing = ProgramAttendance::query()
            ->where('program_registration_id', $registration->id)
            ->whereDate('training_date', $date)
            ->first();

        if ($existing === null) {
            return;
        }

        $old = $existing->status?->value;
        $existing->delete();

        $this->auditAttendanceChange(
            $registration,
            $date,
            $old,
            null,
            $actor,
            'reset',
        );
    }

    /**
     * Bulk set status for many registrations on one day (idempotent).
     *
     * @param  list<int|string>|Collection<int, int|string>  $registrationIds
     * @return int Number of registrations updated
     */
    public function bulkMarkDay(
        TrainingProgram $program,
        iterable $registrationIds,
        string $date,
        AttendanceStatus $status,
        ?User $actor = null,
    ): int {
        if (! $this->isValidAttendancePrepDate($program, $date)) {
            throw ValidationException::withMessages([
                'training_date' => 'اليوم المحدد ليس من أيام التحضير لهذا البرنامج.',
            ]);
        }

        $ids = collect($registrationIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $updated = 0;

        ProgramRegistration::query()
            ->where('training_program_id', $program->id)
            ->whereIn('id', $ids)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->each(function (ProgramRegistration $registration) use ($date, $status, $actor, &$updated): void {
                $this->upsertStatus($registration, $date, $status, null, $actor, 'bulk');
                $updated++;
            });

        return $updated;
    }

    /**
     * Bulk clear (غير محدد) for selected registrations on one day.
     *
     * @param  list<int|string>|Collection<int, int|string>  $registrationIds
     */
    public function bulkClearDay(
        TrainingProgram $program,
        iterable $registrationIds,
        string $date,
        ?User $actor = null,
    ): int {
        if (! $this->isValidAttendancePrepDate($program, $date)) {
            throw ValidationException::withMessages([
                'training_date' => 'اليوم المحدد ليس من أيام التحضير لهذا البرنامج.',
            ]);
        }

        $ids = collect($registrationIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $updated = 0;

        ProgramRegistration::query()
            ->where('training_program_id', $program->id)
            ->whereIn('id', $ids)
            ->each(function (ProgramRegistration $registration) use ($date, $actor, &$updated): void {
                $before = ProgramAttendance::query()
                    ->where('program_registration_id', $registration->id)
                    ->whereDate('training_date', $date)
                    ->exists();

                $this->clearDay($registration, $date, $actor);

                if ($before) {
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Mark all approved/completed registrations absent for a day (اعتماد غياب اليوم).
     * Only fills missing / non-absent rows that are still unspecified; does not overwrite present/late/excused
     * unless $force is true. Default: only create absent for registrations with no row (unspecified).
     */
    public function adoptAbsentForUnspecified(
        TrainingProgram $program,
        string $date,
        ?User $actor = null,
    ): int {
        if (! $this->isValidAttendancePrepDate($program, $date)) {
            throw ValidationException::withMessages([
                'training_date' => 'اليوم المحدد ليس من أيام التحضير لهذا البرنامج.',
            ]);
        }

        $updated = 0;

        $program->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->each(function (ProgramRegistration $registration) use ($date, $actor, &$updated): void {
                $exists = ProgramAttendance::query()
                    ->where('program_registration_id', $registration->id)
                    ->whereDate('training_date', $date)
                    ->exists();

                if ($exists) {
                    return;
                }

                $this->upsertStatus(
                    $registration,
                    $date,
                    AttendanceStatus::Absent,
                    'اعتماد غياب اليوم',
                    $actor,
                    'adopt_absent',
                );
                $updated++;
            });

        return $updated;
    }

    /**
     * Extract program/registration IDs from a KAFAAT pass string or QR payload URL.
     *
     * @return array{program_id: int, registration_id: int}|null
     */
    public function parsePassPayload(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/KAFAAT-P(\d+)-R(\d+)/i', $raw, $matches) !== 1) {
            return null;
        }

        return [
            'program_id' => (int) $matches[1],
            'registration_id' => (int) $matches[2],
        ];
    }

    /**
     * Mark Present from a remote live check-in session for today's (or given) prep day.
     */
    public function markPresentFromLiveSession(
        ProgramRegistration $registration,
        ?string $prepDate = null,
        ?User $actor = null,
    ): ProgramAttendance {
        $registration->loadMissing('trainingProgram');
        $program = $registration->trainingProgram;

        $date = $prepDate ?? Carbon::today(config('app.timezone'))->toDateString();

        if ($program === null || ! $this->isValidAttendancePrepDate($program, $date)) {
            throw ValidationException::withMessages([
                'training_date' => 'اليوم المحدد ليس من أيام التحضير لهذا البرنامج.',
            ]);
        }

        return $this->upsertStatus(
            $registration,
            $date,
            AttendanceStatus::Present,
            'تسجيل حضور ذاتي',
            $actor,
            'live_session',
        );
    }

    /**
     * Mark attendance Present from a scanned/typed KAFAAT pass for a specific prep day.
     *
     * @return array{
     *     ok: bool,
     *     reason: string,
     *     message: string,
     *     beneficiary_name: ?string,
     *     attendance: ?ProgramAttendance
     * }
     */
    public function markPresentFromPass(
        TrainingProgram $program,
        string $rawPass,
        ?ProgramAttendanceChecker $checker = null,
        ?User $admin = null,
        ?string $prepDate = null,
    ): array {
        $parsed = $this->parsePassPayload($rawPass);

        if ($parsed === null) {
            return $this->gateResult(false, 'invalid_pass', 'رمز المرور غير صالح.', null, null);
        }

        if ($program->delivery_mode?->hasPhysicalComponent() !== true) {
            return $this->gateResult(false, 'not_in_person', 'مسح QR متاح للبرامج الحضورية فقط.', null, null);
        }

        if ($parsed['program_id'] !== (int) $program->id) {
            return $this->gateResult(false, 'wrong_program', 'هذا المرور لا يخص هذا البرنامج.', null, null);
        }

        $date = $prepDate ?? Carbon::today(config('app.timezone'))->toDateString();

        if (! $this->isValidAttendancePrepDate($program, $date)) {
            return $this->gateResult(false, 'invalid_day', 'يوم التحضير غير صالح لهذا البرنامج.', null, null);
        }

        $registration = ProgramRegistration::query()
            ->with('user')
            ->whereKey($parsed['registration_id'])
            ->where('training_program_id', $program->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->first();

        if ($registration === null) {
            return $this->gateResult(false, 'not_eligible', 'لا يوجد تسجيل مقبول مرتبط بهذا المرور.', null, null);
        }

        $beneficiaryName = $registration->user?->name ?? 'مستفيدة';

        $existing = ProgramAttendance::query()
            ->where('program_registration_id', $registration->id)
            ->whereDate('training_date', $date)
            ->first();

        if ($existing !== null && in_array($existing->status, AttendanceStatus::attendedCases(), true)) {
            return $this->gateResult(
                true,
                'already_present',
                'تم تسجيل حضور '.$beneficiaryName.' مسبقاً لهذا اليوم.',
                $beneficiaryName,
                $existing,
            );
        }

        $noteParts = ['تحضير بوابة QR', 'اليوم: '.$date];
        if ($checker !== null) {
            $noteParts[] = 'متحضّرة #'.$checker->id.' — '.$checker->name;
        }
        if ($admin !== null) {
            $noteParts[] = 'أدمن #'.$admin->id.' — '.$admin->name;
        }

        $attendance = $this->upsertStatus(
            $registration,
            $date,
            AttendanceStatus::Present,
            implode(' | ', $noteParts),
            $admin,
            'qr',
        );

        return $this->gateResult(
            true,
            'marked',
            'تم تسجيل حضور '.$beneficiaryName.' بنجاح.',
            $beneficiaryName,
            $attendance,
        );
    }

    private function upsertStatus(
        ProgramRegistration $registration,
        string $date,
        AttendanceStatus $status,
        ?string $notes,
        ?User $actor,
        string $source,
    ): ProgramAttendance {
        $existing = ProgramAttendance::query()
            ->where('program_registration_id', $registration->id)
            ->whereDate('training_date', $date)
            ->first();

        $oldStatus = $existing?->status?->value;

        $payload = [
            'status' => $status,
            'training_date' => $date,
        ];
        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        if ($existing !== null) {
            $existing->fill($payload);
            $existing->save();
            $attendance = $existing;
        } else {
            $attendance = ProgramAttendance::query()->create([
                'program_registration_id' => $registration->id,
                ...$payload,
            ]);
        }

        if ($oldStatus !== $status->value) {
            $this->auditAttendanceChange(
                $registration,
                $date,
                $oldStatus,
                $status->value,
                $actor,
                $source,
            );
        }

        return $attendance;
    }

    private function auditAttendanceChange(
        ProgramRegistration $registration,
        string $date,
        ?string $oldStatus,
        ?string $newStatus,
        ?User $actor,
        string $source,
    ): void {
        $actor ??= Auth::user();

        $this->auditLogger->record(
            $actor instanceof User ? $actor : null,
            $oldStatus === null && $newStatus !== null
                ? 'program_attendance.created'
                : ($newStatus === null ? 'program_attendance.cleared' : 'program_attendance.updated'),
            AuditLogResult::Success,
            targetUser: $registration->user_id
                ? User::query()->find($registration->user_id)
                : null,
            resource: $registration,
            metadata: [
                'program_registration_id' => $registration->id,
                'training_program_id' => $registration->training_program_id,
                'training_date' => $date,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'source' => $source,
            ],
        );
    }

    /**
     * @return array{
     *     ok: bool,
     *     reason: string,
     *     message: string,
     *     beneficiary_name: ?string,
     *     attendance: ?ProgramAttendance
     * }
     */
    private function gateResult(
        bool $ok,
        string $reason,
        string $message,
        ?string $beneficiaryName,
        ?ProgramAttendance $attendance,
    ): array {
        return [
            'ok' => $ok,
            'reason' => $reason,
            'message' => $message,
            'beneficiary_name' => $beneficiaryName,
            'attendance' => $attendance,
        ];
    }
}

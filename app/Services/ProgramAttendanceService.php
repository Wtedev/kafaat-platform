<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\AuditLogResult;
use App\Enums\ProgramPrepDayType;
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
use Illuminate\Support\Facades\DB;
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
     * All program prep days (every row counts for attendance), chronological Y-m-d strings.
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
                ->sortBy(fn (ProgramPrepDay $day): string => $day->dateString())
                ->values();
        }

        return $program->prepDays()->chronological()->get();
    }

    public function prepDayForDate(TrainingProgram $program, string $date): ?ProgramPrepDay
    {
        return $this->attendancePrepDays($program)
            ->first(fn (ProgramPrepDay $day): bool => $day->dateString() === $date);
    }

    public function todayPrepDay(TrainingProgram $program, ?Carbon $asOf = null): ?ProgramPrepDay
    {
        $today = ($asOf ?? Carbon::today(config('app.timezone')))->toDateString();

        return $this->prepDayForDate($program, $today);
    }

    public function isTodayInPersonPrepDay(TrainingProgram $program, ?Carbon $asOf = null): bool
    {
        $day = $this->todayPrepDay($program, $asOf);

        return $day !== null && $day->delivery_type === ProgramPrepDayType::InPerson;
    }

    public function isTodayRemotePrepDay(TrainingProgram $program, ?Carbon $asOf = null): bool
    {
        $day = $this->todayPrepDay($program, $asOf);

        return $day !== null && $day->delivery_type === ProgramPrepDayType::Remote;
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
     * Kept for API compatibility; never pre-creates absent rows.
     */
    public function generateSessions(ProgramRegistration $registration): int
    {
        return 0;
    }

    /**
     * Kept for API compatibility; never pre-creates absent rows.
     */
    public function generateSessionsForAllRegistrations(TrainingProgram $program): int
    {
        return 0;
    }

    /**
     * Attendance % = present days / all program prep days (including future) × 100.
     * Returns null when the program has no prep days (UI shows «—»).
     */
    public function calculatePercentage(ProgramRegistration $registration, ?Carbon $asOf = null): ?float
    {
        $registration->loadMissing('trainingProgram');
        $totalDays = $this->countExpectedTrainingDays($registration->trainingProgram);

        if ($totalDays === 0) {
            return null;
        }

        $attended = $this->countAttendedDays($registration);

        return round($attended / $totalDays * 100, 2);
    }

    public function countExpectedTrainingDays(TrainingProgram $program): int
    {
        return count($this->attendancePrepDateStrings($program));
    }

    public function countAttendedDays(ProgramRegistration $registration): int
    {
        $registration->loadMissing('trainingProgram');
        $dates = $this->attendancePrepDateStrings($registration->trainingProgram);

        if ($dates === []) {
            return 0;
        }

        return $registration->attendanceRecords()
            ->where('status', AttendanceStatus::Present->value)
            ->get()
            ->filter(fn (ProgramAttendance $row): bool => in_array(
                $row->training_date->toDateString(),
                $dates,
                true,
            ))
            ->count();
    }

    /**
     * @return array{total: int, present: int, not_present: int}
     */
    public function getSummary(ProgramRegistration $registration): array
    {
        $dates = $this->attendancePrepDateStrings($registration->trainingProgram);
        $total = count($dates);
        $present = $this->countAttendedDays($registration);

        return [
            'total' => $total,
            'present' => $present,
            'not_present' => max(0, $total - $present),
        ];
    }

    public function dayName(int $dayOfWeek): string
    {
        return self::DAY_NAMES[$dayOfWeek] ?? (string) $dayOfWeek;
    }

    public function isPresentOnDate(ProgramRegistration $registration, string $date): bool
    {
        return $this->statusForDate($registration, $date) === AttendanceStatus::Present;
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

    public function displayLabelForDate(ProgramRegistration $registration, string $date): string
    {
        return $this->isPresentOnDate($registration, $date) ? 'حاضر' : 'لم يحضر';
    }

    /**
     * Manual mark present for any prep day.
     */
    public function markPresent(
        ProgramRegistration $registration,
        string $date,
        ?User $actor = null,
        string $source = 'manual',
        ?string $notes = null,
    ): ProgramAttendance {
        $registration->loadMissing('trainingProgram');
        $program = $registration->trainingProgram;

        if ($program === null || ! $this->isValidAttendancePrepDate($program, $date)) {
            throw ValidationException::withMessages([
                'training_date' => 'اليوم المحدد ليس من أيام البرنامج.',
            ]);
        }

        return $this->upsertPresent($registration, $date, $notes, $actor, $source);
    }

    /**
     * Compatibility wrapper: Present marks attendance; any other status clears the row («لم يحضر»).
     * Prefer markPresent() / clearDay() / setPresentState() for new call sites.
     */
    public function markManualDay(
        ProgramRegistration $registration,
        string $date,
        AttendanceStatus $status,
        ?string $notes = null,
        ?User $actor = null,
    ): ?ProgramAttendance {
        if ($status !== AttendanceStatus::Present) {
            $this->clearDay($registration, $date, $actor);

            return null;
        }

        return $this->markPresent($registration, $date, $actor, 'manual', $notes);
    }

    /**
     * Unmark presence: delete the attendance row («لم يحضر») + audit.
     */
    public function clearDay(
        ProgramRegistration $registration,
        string $date,
        ?User $actor = null,
        string $source = 'manual',
        ?ProgramAttendanceChecker $checker = null,
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
            $source,
            $checker,
        );
    }

    /**
     * Toggle present / not-present for manual UI.
     */
    public function setPresentState(
        ProgramRegistration $registration,
        string $date,
        bool $present,
        ?User $actor = null,
    ): void {
        if ($present) {
            $this->markPresent($registration, $date, $actor, 'manual');

            return;
        }

        $this->clearDay($registration, $date, $actor);
    }

    /**
     * Checker portal: present/absent toggle for a whitelisted program prep day.
     * Null date defaults to server today; non-prep dates are rejected.
     *
     * @return array{ok: bool, reason: string, message: string, present: bool, beneficiary_name: ?string}
     */
    public function setPresentStateByChecker(
        TrainingProgram $program,
        ProgramRegistration $registration,
        bool $present,
        ProgramAttendanceChecker $checker,
        ?string $requestedDate = null,
    ): array {
        if ((int) $registration->training_program_id !== (int) $program->id) {
            return [
                'ok' => false,
                'reason' => 'wrong_program',
                'message' => 'هذا التسجيل لا يخص هذا البرنامج.',
                'present' => false,
                'beneficiary_name' => null,
            ];
        }

        if (! in_array($registration->status, [
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true)) {
            return [
                'ok' => false,
                'reason' => 'not_eligible',
                'message' => 'لا يمكن تحضير تسجيل غير مقبول.',
                'present' => false,
                'beneficiary_name' => null,
            ];
        }

        $date = $requestedDate ?: Carbon::today(config('app.timezone'))->toDateString();

        if (! $this->isValidAttendancePrepDate($program, $date)) {
            return [
                'ok' => false,
                'reason' => 'invalid_day',
                'message' => 'اليوم المحدد ليس من أيام البرنامج، ولا يتوفر تحضير.',
                'present' => false,
                'beneficiary_name' => null,
            ];
        }

        $registration->loadMissing('user');
        $beneficiaryName = $registration->user?->fullName() ?: ($registration->user?->name ?? 'مستفيد');

        return DB::transaction(function () use ($registration, $date, $present, $checker, $beneficiaryName): array {
            $locked = ProgramRegistration::query()
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return [
                    'ok' => false,
                    'reason' => 'not_found',
                    'message' => 'تعذّر العثور على التسجيل.',
                    'present' => false,
                    'beneficiary_name' => null,
                ];
            }

            if ($present) {
                $this->upsertPresent(
                    $locked,
                    $date,
                    'تحضير يدوي — مسؤول #'.$checker->id.' — '.$checker->name,
                    null,
                    'checker_manual',
                    $checker,
                );
            } else {
                $this->clearDay($locked, $date, null, 'checker_manual', $checker);
            }

            return [
                'ok' => true,
                'reason' => $present ? 'marked' : 'cleared',
                'message' => $present
                    ? 'تم تسجيل الحضور.'
                    : 'تم إلغاء الحضور.',
                'present' => $present,
                'beneficiary_name' => $beneficiaryName,
            ];
        });
    }

    /**
     * Bulk mark present for selected registrations on one day.
     *
     * @param  list<int|string>|Collection<int, int|string>  $registrationIds
     */
    public function bulkMarkPresent(
        TrainingProgram $program,
        iterable $registrationIds,
        string $date,
        ?User $actor = null,
    ): int {
        if (! $this->isValidAttendancePrepDate($program, $date)) {
            throw ValidationException::withMessages([
                'training_date' => 'اليوم المحدد ليس من أيام البرنامج.',
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
            ->each(function (ProgramRegistration $registration) use ($date, $actor, &$updated): void {
                $this->upsertPresent($registration, $date, null, $actor, 'bulk');
                $updated++;
            });

        return $updated;
    }

    /**
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
                'training_date' => 'اليوم المحدد ليس من أيام البرنامج.',
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
     * Mark Present from a remote live check-in session for the session's prep day.
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
                'training_date' => 'اليوم المحدد ليس من أيام البرنامج.',
            ]);
        }

        $prepDay = $this->prepDayForDate($program, $date);
        if ($prepDay === null || $prepDay->delivery_type !== ProgramPrepDayType::Remote) {
            throw ValidationException::withMessages([
                'training_date' => 'التحضير عن بُعد متاح فقط في أيام البرنامج عن بُعد.',
            ]);
        }

        return $this->upsertPresent(
            $registration,
            $date,
            'تسجيل حضور ذاتي',
            $actor,
            'remote_session',
        );
    }

    /**
     * Mark attendance Present from a scanned/typed KAFAAT pass for a whitelisted prep day.
     * Null prepDate defaults to server today; non-prep dates are rejected.
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

        if ($parsed['program_id'] !== (int) $program->id) {
            return $this->gateResult(false, 'wrong_program', 'هذا المرور لا يخص هذا البرنامج.', null, null);
        }

        $date = $prepDate ?: Carbon::today(config('app.timezone'))->toDateString();
        $prepDay = $this->prepDayForDate($program, $date);

        if ($prepDay === null) {
            return $this->gateResult(
                false,
                'invalid_day',
                'اليوم المحدد ليس من أيام البرنامج. لا يمكن تسجيل الحضور عبر QR.',
                null,
                null,
            );
        }

        if ($prepDay->delivery_type !== ProgramPrepDayType::InPerson) {
            return $this->gateResult(
                false,
                'not_in_person',
                'مسح QR متاح فقط في الأيام الحضورية للبرنامج.',
                null,
                null,
            );
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

        $beneficiaryName = $registration->user?->fullName()
            ?: ($registration->user?->name ?? 'مستفيد');

        $existing = ProgramAttendance::query()
            ->where('program_registration_id', $registration->id)
            ->whereDate('training_date', $date)
            ->where('status', AttendanceStatus::Present->value)
            ->first();

        if ($existing !== null) {
            return $this->gateResult(
                true,
                'already_present',
                'تم تسجيل الحضور مسبقاً.',
                $beneficiaryName,
                $existing,
            );
        }

        $noteParts = ['تحضير بوابة QR', 'اليوم: '.$date];
        if ($checker !== null) {
            $noteParts[] = 'مسؤول التحضير #'.$checker->id.' — '.$checker->name;
        }
        if ($admin !== null) {
            $noteParts[] = 'أدمن #'.$admin->id.' — '.$admin->name;
        }

        $source = $checker !== null ? 'checker_qr' : 'qr';

        $attendance = $this->upsertPresent(
            $registration,
            $date,
            implode(' | ', $noteParts),
            $admin,
            $source,
            $checker,
        );

        return $this->gateResult(
            true,
            'marked',
            'تم تسجيل الحضور.',
            $beneficiaryName,
            $attendance,
        );
    }

    private function upsertPresent(
        ProgramRegistration $registration,
        string $date,
        ?string $notes,
        ?User $actor,
        string $source,
        ?ProgramAttendanceChecker $checker = null,
    ): ProgramAttendance {
        $existing = ProgramAttendance::query()
            ->where('program_registration_id', $registration->id)
            ->whereDate('training_date', $date)
            ->first();

        $oldStatus = $existing?->status?->value;
        $status = AttendanceStatus::Present;

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
                $checker,
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
        ?ProgramAttendanceChecker $checker = null,
    ): void {
        $actor ??= Auth::user();

        $metadata = [
            'program_registration_id' => $registration->id,
            'training_program_id' => $registration->training_program_id,
            'training_date' => $date,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'source' => $source,
        ];

        if ($checker !== null) {
            $metadata['checker_id'] = $checker->id;
            $metadata['checker_name'] = $checker->name;
        }

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
            metadata: $metadata,
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

<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\RegistrationStatus;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;

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

    /**
     * Generate expected training sessions for a single registration.
     *
     * Iterates over every date in the program's [start_date, end_date] range
     * and creates a ProgramAttendance row (status = absent) for each date
     * whose day-of-week matches one of the program's configured weekdays.
     *
     * Existing records are NOT overwritten (idempotent).
     *
     * @return int Number of NEW rows inserted (0 means nothing was created,
     *             either because weekdays/dates are not configured or all
     *             sessions already existed).
     */
    public function generateSessions(ProgramRegistration $registration): int
    {
        $registration->loadMissing('trainingProgram');
        $program = $registration->trainingProgram;

        if (
            $program->start_date === null
            || $program->end_date === null
            || empty($program->weekdays)
        ) {
            return 0;
        }

        // Weekdays are stored as strings by Filament CheckboxList; cast to int
        // so they match Carbon's dayOfWeek integer values.
        $weekdays = array_map('intval', $program->weekdays);

        $current = $program->start_date->copy();
        $end = $program->end_date->copy();
        $created = 0;

        while ($current->lte($end)) {
            if (in_array($current->dayOfWeek, $weekdays, strict: true)) {
                $record = ProgramAttendance::firstOrCreate(
                    [
                        'program_registration_id' => $registration->id,
                        'training_date' => $current->toDateString(),
                    ],
                    [
                        'status' => AttendanceStatus::Absent,
                    ]
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                }
            }

            $current->addDay();
        }

        return $created;
    }

    /**
     * Generate sessions for ALL approved and completed registrations of a program.
     *
     * @return int Total new rows inserted across all registrations.
     */
    public function generateSessionsForAllRegistrations(TrainingProgram $program): int
    {
        $total = 0;

        $program->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->each(function (ProgramRegistration $registration) use (&$total): void {
                $total += $this->generateSessions($registration);
            });

        return $total;
    }

    /**
     * Calculate the attendance percentage for a registration from its daily records.
     *
     * Returns null when no attendance records exist (fall back to stored value).
     * Excused absences count as absent for percentage purposes.
     */
    public function calculatePercentage(ProgramRegistration $registration): ?float
    {
        $registration->loadMissing('trainingProgram');
        $expectedDays = $this->countExpectedTrainingDays($registration->trainingProgram);

        if ($expectedDays === 0) {
            return null;
        }

        $present = $registration->attendanceRecords()
            ->where('status', AttendanceStatus::Present->value)
            ->count();

        return round($present / $expectedDays * 100, 2);
    }

    public function countExpectedTrainingDays(TrainingProgram $program): int
    {
        return count(app(PathAttendanceService::class)->expectedDatesForProgram($program));
    }

    /**
     * Return a summary array: ['total' => int, 'present' => int, 'absent' => int, 'excused' => int].
     */
    public function getSummary(ProgramRegistration $registration): array
    {
        $records = $registration->attendanceRecords()
            ->select('status')
            ->get()
            ->groupBy('status');

        return [
            'total' => $records->sum(fn ($g) => $g->count()),
            'present' => $records->get(AttendanceStatus::Present->value)?->count() ?? 0,
            'absent' => $records->get(AttendanceStatus::Absent->value)?->count() ?? 0,
            'excused' => $records->get(AttendanceStatus::Excused->value)?->count() ?? 0,
        ];
    }

    /**
     * Translate a dayOfWeek integer (0–6) to an Arabic day name.
     */
    public function dayName(int $dayOfWeek): string
    {
        return self::DAY_NAMES[$dayOfWeek] ?? (string) $dayOfWeek;
    }

    public function markManualDay(ProgramRegistration $registration, string $date, AttendanceStatus $status, ?string $notes = null): void
    {
        ProgramAttendance::updateOrCreate(
            [
                'program_registration_id' => $registration->id,
                'training_date' => $date,
            ],
            [
                'status' => $status,
                'notes' => $notes,
            ],
        );
    }

    /**
     * Extract program/registration IDs from a KAFAAT pass string or QR payload URL.
     *
     * Accepts raw codes like `KAFAAT-P12-R34` and full URLs that include
     * `#KAFAAT-P12-R34` (or the code anywhere in the scanned text).
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
     * Mark today's attendance Present from a scanned/typed KAFAAT pass.
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
    ): array {
        $parsed = $this->parsePassPayload($rawPass);

        if ($parsed === null) {
            return $this->gateResult(false, 'invalid_pass', 'رمز المرور غير صالح.', null, null);
        }

        if ($program->delivery_mode !== ProgramDeliveryMode::InPerson) {
            return $this->gateResult(false, 'not_in_person', 'مسح QR متاح للبرامج الحضورية فقط.', null, null);
        }

        if ($parsed['program_id'] !== (int) $program->id) {
            return $this->gateResult(false, 'wrong_program', 'هذا المرور لا يخص هذا البرنامج.', null, null);
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
        $today = today()->toDateString();

        $existing = ProgramAttendance::query()
            ->where('program_registration_id', $registration->id)
            ->whereDate('training_date', $today)
            ->first();

        if ($existing !== null && $existing->status === AttendanceStatus::Present) {
            return $this->gateResult(
                true,
                'already_present',
                'تم تسجيل حضور '.$beneficiaryName.' مسبقاً اليوم.',
                $beneficiaryName,
                $existing,
            );
        }

        $noteParts = ['تحضير بوابة QR'];
        if ($checker !== null) {
            $noteParts[] = 'متحضّرة #'.$checker->id.' — '.$checker->name;
        }
        if ($admin !== null) {
            $noteParts[] = 'أدمن #'.$admin->id.' — '.$admin->name;
        }

        $attendance = ProgramAttendance::updateOrCreate(
            [
                'program_registration_id' => $registration->id,
                'training_date' => $today,
            ],
            [
                'status' => AttendanceStatus::Present,
                'notes' => implode(' | ', $noteParts),
            ],
        );

        return $this->gateResult(
            true,
            'marked',
            'تم تسجيل حضور '.$beneficiaryName.' بنجاح.',
            $beneficiaryName,
            $attendance,
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

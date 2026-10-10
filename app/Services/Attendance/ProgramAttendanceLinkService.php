<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceMarkSource;
use App\Enums\RegistrationStatus;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProgramAttendanceLinkService
{
    public const CLOSED_MESSAGE = 'التحضير غير مفتوح الآن.';

    public const CANCELLED_MESSAGE = 'رابط التحضير ملغى.';

    public const NOT_APPROVED_MESSAGE = 'تعذّر تسجيل الحضور. القبول في البرنامج مطلوب.';

    public const ALREADY_MESSAGE = 'سبق تسجيل حضورك في هذا الرابط.';

    public const OPEN_MINUTES = 15;

    public function create(TrainingProgram $program, string $name): ProgramAttendanceLink
    {
        return $program->attendanceLinks()->create([
            'name' => trim($name),
        ]);
    }

    public function cancel(ProgramAttendanceLink $link): void
    {
        $link->update([
            'cancelled_at' => now(),
            'closes_at' => $link->isOpen() ? now() : $link->closes_at,
        ]);
    }

    public function open(ProgramAttendanceLink $link): ProgramAttendanceLink
    {
        if ($link->isCancelled()) {
            throw ValidationException::withMessages([
                'attendance' => self::CANCELLED_MESSAGE,
            ]);
        }

        $link->update([
            'opens_at' => now(),
            'closes_at' => now()->addMinutes(self::OPEN_MINUTES),
        ]);

        return $link->refresh();
    }

    public function close(ProgramAttendanceLink $link): ProgramAttendanceLink
    {
        if ($link->isOpen()) {
            $link->update(['closes_at' => now()]);
        }

        return $link->refresh();
    }

    public function checkIn(ProgramAttendanceLink $link, User $user): ProgramAttendanceMark
    {
        return $this->store($link, $user, AttendanceMarkSource::Self, requireOpen: true);
    }

    public function markManual(ProgramAttendanceLink $link, ProgramRegistration $registration): ProgramAttendanceMark
    {
        $registration->loadMissing('user');

        if ((int) $registration->training_program_id !== (int) $link->training_program_id) {
            throw ValidationException::withMessages([
                'attendance' => self::NOT_APPROVED_MESSAGE,
            ]);
        }

        return $this->store($link, $registration->user, AttendanceMarkSource::Manual, requireOpen: false, registration: $registration);
    }

    private function store(
        ProgramAttendanceLink $link,
        User $user,
        AttendanceMarkSource $source,
        bool $requireOpen,
        ?ProgramRegistration $registration = null,
    ): ProgramAttendanceMark {
        try {
            return DB::transaction(function () use ($link, $user, $source, $requireOpen, $registration): ProgramAttendanceMark {
                $locked = ProgramAttendanceLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();

                if ($locked->isCancelled()) {
                    throw ValidationException::withMessages([
                        'attendance' => self::CANCELLED_MESSAGE,
                    ]);
                }

                if ($requireOpen && ! $locked->isOpen()) {
                    throw ValidationException::withMessages([
                        'attendance' => self::CLOSED_MESSAGE,
                    ]);
                }

                $registration ??= ProgramRegistration::query()
                    ->where('training_program_id', $locked->training_program_id)
                    ->where('user_id', $user->id)
                    ->where('status', RegistrationStatus::Approved->value)
                    ->first();

                if ($registration === null || $registration->status !== RegistrationStatus::Approved || (int) $registration->user_id !== (int) $user->id) {
                    throw ValidationException::withMessages([
                        'attendance' => self::NOT_APPROVED_MESSAGE,
                    ]);
                }

                $existing = $locked->marks()->where('program_registration_id', $registration->id)->exists();
                if ($existing) {
                    throw ValidationException::withMessages([
                        'attendance' => self::ALREADY_MESSAGE,
                    ]);
                }

                return $locked->marks()->create([
                    'program_registration_id' => $registration->id,
                    'attended_at' => now(),
                    'source' => $source,
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'attendance' => self::ALREADY_MESSAGE,
                ]);
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique');
    }
}

<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceMarkSource;
use App\Enums\RegistrationStatus;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProgramAttendanceLinkService
{
    public const CLOSED_MESSAGE = 'التحضير غير مفتوح الآن.';

    public const UNAVAILABLE_MESSAGE = 'رابط التحضير غير متاح الآن';

    public const CANCELLED_MESSAGE = 'رابط التحضير ملغى.';

    public const NOT_APPROVED_MESSAGE = 'تعذّر تسجيل الحضور. القبول في البرنامج مطلوب.';

    public const NOT_FOUND_MESSAGE = 'لم نجد تسجيلاً مقبولاً بهذا الرقم في البرنامج. تواصل مع فريق البرنامج';

    public const ALREADY_MESSAGE = 'سبق تسجيل حضورك في هذا الرابط.';

    public const ALREADY_PUBLIC_MESSAGE = 'حضورك مسجّل مسبقاً';

    public const RECORDED_MESSAGE = 'تم تسجيل حضورك';

    public const OPEN_MINUTES = 15;

    public function create(TrainingProgram $program, string $name, int $openMinutes = self::OPEN_MINUTES): ProgramAttendanceLink
    {
        return $program->attendanceLinks()->create([
            'name' => trim($name),
            'open_minutes' => max(1, $openMinutes),
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
            'closes_at' => now()->addMinutes($link->openMinutes()),
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

    /**
     * @return array{status: 'match'|'missing'|'already'|'closed', short_name: ?string, registration_id: ?int, attended_at: ?string}
     */
    public function identify(ProgramAttendanceLink $link, string $nationalId): array
    {
        if (! $link->isOpen()) {
            return ['status' => 'closed', 'short_name' => null, 'registration_id' => null, 'attended_at' => null];
        }

        $normalized = IdentityNumberService::normalize($nationalId);
        $lookup = is_string($normalized) && IdentityNumberService::isValidFormat($normalized)
            ? IdentityNumberService::generateLookupHash($normalized)
            : hash('sha256', 'attendance-miss');

        $registration = ProgramRegistration::query()
            ->where('training_program_id', $link->training_program_id)
            ->where('status', RegistrationStatus::Approved->value)
            ->whereHas('user', fn ($query) => $query->where('identity_number_lookup_hash', $lookup))
            ->first();

        if ($registration === null) {
            return ['status' => 'missing', 'short_name' => null, 'registration_id' => null, 'attended_at' => null];
        }

        $existing = $link->marks()->where('program_registration_id', $registration->id)->first();
        if ($existing !== null) {
            return [
                'status' => 'already',
                'short_name' => null,
                'registration_id' => $registration->id,
                'attended_at' => $existing->riyadhLabel(),
            ];
        }

        $registration->loadMissing('user');

        return [
            'status' => 'match',
            'short_name' => app(ProgramSurveyService::class)->shortName($registration->user),
            'registration_id' => $registration->id,
            'attended_at' => null,
        ];
    }

    public function confirm(ProgramAttendanceLink $link, int $registrationId, ?string $ip): ProgramAttendanceMark
    {
        $registration = ProgramRegistration::query()->whereKey($registrationId)->first();
        $registration?->loadMissing('user');

        if ($registration === null || $registration->user === null) {
            throw ValidationException::withMessages([
                'attendance' => self::NOT_FOUND_MESSAGE,
            ]);
        }

        try {
            return $this->store($link, $registration->user, AttendanceMarkSource::PublicLink, requireOpen: true, registration: $registration, ip: $ip);
        } catch (ValidationException $exception) {
            $message = (string) collect($exception->errors())->flatten()->first();
            if ($message === self::ALREADY_MESSAGE) {
                throw ValidationException::withMessages([
                    'attendance' => self::ALREADY_PUBLIC_MESSAGE,
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Staff lookup for one identity on this link. A full valid number that is not an
     * approved registration on the program returns the same not-found message as the public page.
     *
     * @return array{name: ?string, registration: ?ProgramRegistration, message: ?string, ready: bool}
     */
    public function manualCandidate(ProgramAttendanceLink $link, string $nationalId): array
    {
        $empty = ['name' => null, 'registration' => null, 'message' => null, 'ready' => false];
        $normalized = IdentityNumberService::normalize($nationalId);

        if ($normalized === null || $normalized === '') {
            return $empty;
        }

        if (! IdentityNumberService::isValidFormat($normalized)) {
            return $empty;
        }

        $registration = ProgramRegistration::query()
            ->where('training_program_id', $link->training_program_id)
            ->where('status', RegistrationStatus::Approved->value)
            ->whereHas('user', fn ($query) => $query->where(
                'identity_number_lookup_hash',
                IdentityNumberService::generateLookupHash($normalized),
            ))
            ->with('user')
            ->first();

        if ($registration === null) {
            return [
                'name' => null,
                'registration' => null,
                'message' => self::NOT_FOUND_MESSAGE,
                'ready' => false,
            ];
        }

        $name = $registration->user?->fullName();
        $already = $link->marks()->where('program_registration_id', $registration->id)->exists();

        return [
            'name' => $name,
            'registration' => $registration,
            'message' => $already ? self::ALREADY_MESSAGE : null,
            'ready' => ! $already,
        ];
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
        ?string $ip = null,
    ): ProgramAttendanceMark {
        try {
            return DB::transaction(function () use ($link, $user, $source, $requireOpen, $registration, $ip): ProgramAttendanceMark {
                $locked = ProgramAttendanceLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();

                if ($locked->isCancelled()) {
                    throw ValidationException::withMessages([
                        'attendance' => self::CANCELLED_MESSAGE,
                    ]);
                }

                if ($requireOpen && ! $locked->isOpen()) {
                    throw ValidationException::withMessages([
                        'attendance' => self::UNAVAILABLE_MESSAGE,
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
                    'ip_address' => $ip,
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

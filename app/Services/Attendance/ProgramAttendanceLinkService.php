<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceLinkMode;
use App\Enums\AttendanceMarkSource;
use App\Enums\RegistrationStatus;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\ProgramAttendanceService;
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

    public function create(
        TrainingProgram $program,
        string $name,
        int $openMinutes = self::OPEN_MINUTES,
        AttendanceLinkMode $mode = AttendanceLinkMode::Remote,
    ): ProgramAttendanceLink {
        return $program->attendanceLinks()->create([
            'name' => trim($name),
            'open_minutes' => max(1, $openMinutes),
            'mode' => $mode,
        ]);
    }

    /**
     * Change how long the link stays open. An open link keeps its start time and gets the new end.
     */
    public function updateMinutes(ProgramAttendanceLink $link, int $openMinutes): ProgramAttendanceLink
    {
        $openMinutes = max(1, $openMinutes);
        $attributes = ['open_minutes' => $openMinutes];

        if ($link->isOpen() && $link->opens_at !== null) {
            $closes = $link->opens_at->copy()->addMinutes($openMinutes);
            $attributes['closes_at'] = $closes->isFuture() ? $closes : now();
        }

        $link->update($attributes);

        return $link->refresh();
    }

    /**
     * QR mode: record attendance from a scanned or typed attendance pass (KAFAAT-P{program}-R{registration}).
     *
     * @return array{ok: bool, message: string, name: ?string, time: ?string}
     */
    public function markFromPass(ProgramAttendanceLink $link, string $rawPass, ?string $ip): array
    {
        if (! $link->isOpen()) {
            return ['ok' => false, 'message' => self::UNAVAILABLE_MESSAGE, 'name' => null, 'time' => null];
        }

        $parsed = app(ProgramAttendanceService::class)->parsePassPayload($rawPass);
        if ($parsed === null) {
            return ['ok' => false, 'message' => 'رمز الحضور غير صالح.', 'name' => null, 'time' => null];
        }

        if ($parsed['program_id'] !== (int) $link->training_program_id) {
            return ['ok' => false, 'message' => 'هذا الرمز لبرنامج آخر.', 'name' => null, 'time' => null];
        }

        $registration = ProgramRegistration::query()
            ->whereKey($parsed['registration_id'])
            ->where('training_program_id', $link->training_program_id)
            ->with('user')
            ->first();

        if ($registration === null || $registration->user === null || ! in_array($registration->status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)) {
            return ['ok' => false, 'message' => self::NOT_APPROVED_MESSAGE, 'name' => null, 'time' => null];
        }

        // The checker at the door needs the full name to match the person in front of them.
        $name = (string) $registration->user->name;
        $existing = $link->marks()->where('program_registration_id', $registration->id)->first();
        if ($existing !== null) {
            return ['ok' => true, 'message' => 'محضَّر من قبل.', 'name' => $name, 'time' => $existing->riyadhLabel()];
        }

        try {
            $mark = $this->store($link, $registration->user, AttendanceMarkSource::Qr, requireOpen: true, registration: $registration, ip: $ip);
        } catch (ValidationException $exception) {
            return ['ok' => false, 'message' => (string) collect($exception->errors())->flatten()->first(), 'name' => $name, 'time' => null];
        }

        return ['ok' => true, 'message' => self::RECORDED_MESSAGE, 'name' => $name, 'time' => $mark->riyadhLabel()];
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

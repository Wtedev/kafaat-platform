<?php

namespace App\Services;

use App\Enums\ProgramDeliveryMode;
use App\Models\ProgramAttendanceChecker;
use App\Models\TrainingProgram;
use App\Notifications\AttendanceCheckerInviteCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProgramAttendanceCheckerInviteService
{
    /** مدة صلاحية الرمز بالدقائق. */
    public const EXPIRES_MINUTES = 15;

    /** أقصى عدد محاولات إدخال خاطئة قبل إبطال الرمز. */
    public const MAX_ATTEMPTS = 5;

    /**
     * يدعو متحضّرة (أو يعيد تفعيلها) ويرسل رمز التحقق.
     */
    public function invite(TrainingProgram $program, string $name, string $email): ProgramAttendanceChecker
    {
        $this->assertInPerson($program);

        $email = strtolower(trim($email));
        $name = trim($name);

        $checker = ProgramAttendanceChecker::query()->updateOrCreate(
            [
                'training_program_id' => $program->id,
                'email' => $email,
            ],
            [
                'name' => $name,
                'is_active' => true,
            ],
        );

        $this->sendCode($checker);

        return $checker->fresh() ?? $checker;
    }

    public function sendCode(ProgramAttendanceChecker $checker): void
    {
        $checker->loadMissing('trainingProgram');
        $program = $checker->trainingProgram;

        if ($program === null) {
            throw ValidationException::withMessages([
                'email' => 'البرنامج المرتبط بالدعوة غير موجود.',
            ]);
        }

        $this->assertInPerson($program);

        if (! $checker->is_active) {
            throw ValidationException::withMessages([
                'email' => 'هذه العضوية معطّلة. فعّليها أولاً ثم أعيدي إرسال الرمز.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $checker->forceFill([
            'invite_code_hash' => Hash::make($code),
            'invite_code_expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'invite_attempts' => 0,
        ])->save();

        $gateUrl = route('gate.login', ['program' => $program->slug], absolute: true);

        $checker->notify(new AttendanceCheckerInviteCode(
            $code,
            self::EXPIRES_MINUTES,
            $program,
            $gateUrl,
        ));
    }

    /**
     * يتحقق من الرمز المُدخل للمتطوعة.
     *
     * @return string 'success' | 'expired' | 'too_many_attempts' | 'invalid' | 'not_found' | 'inactive'
     */
    public function verify(TrainingProgram $program, string $email, string $code): string
    {
        $email = strtolower(trim($email));

        $checker = ProgramAttendanceChecker::query()
            ->where('training_program_id', $program->id)
            ->where('email', $email)
            ->first();

        if ($checker === null) {
            return 'not_found';
        }

        if (! $checker->is_active) {
            return 'inactive';
        }

        if ($checker->invite_code_hash === null || $checker->isInviteExpired()) {
            $checker->forceFill([
                'invite_code_hash' => null,
                'invite_code_expires_at' => null,
                'invite_attempts' => 0,
            ])->save();

            return 'expired';
        }

        if ($checker->invite_attempts >= self::MAX_ATTEMPTS) {
            $checker->forceFill([
                'invite_code_hash' => null,
                'invite_code_expires_at' => null,
                'invite_attempts' => 0,
            ])->save();

            return 'too_many_attempts';
        }

        if (! Hash::check($code, $checker->invite_code_hash)) {
            $checker->increment('invite_attempts');

            return 'invalid';
        }

        $checker->forceFill([
            'invite_code_hash' => null,
            'invite_code_expires_at' => null,
            'invite_attempts' => 0,
            'verified_at' => $checker->verified_at ?? now(),
        ])->save();

        return 'success';
    }

    public function findActiveChecker(TrainingProgram $program, string $email): ?ProgramAttendanceChecker
    {
        return ProgramAttendanceChecker::query()
            ->where('training_program_id', $program->id)
            ->where('email', strtolower(trim($email)))
            ->where('is_active', true)
            ->first();
    }

    private function assertInPerson(TrainingProgram $program): void
    {
        if ($program->delivery_mode?->hasPhysicalComponent() !== true) {
            throw ValidationException::withMessages([
                'email' => 'عضوية التحضير متاحة للبرامج الحضورية فقط.',
            ]);
        }
    }
}

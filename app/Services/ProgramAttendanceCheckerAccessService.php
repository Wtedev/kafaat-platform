<?php

namespace App\Services;

use App\Enums\AuditLogResult;
use App\Models\ProgramAttendanceChecker;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramAttendanceCheckerAccessService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{checker: ProgramAttendanceChecker, token: string, url: string}
     */
    public function create(TrainingProgram $program, string $name, ?User $actor = null): array
    {
        $this->assertHasPrepDays($program);

        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'اسم مسؤول التحضير مطلوب.',
            ]);
        }

        return DB::transaction(function () use ($program, $name, $actor): array {
            $checker = ProgramAttendanceChecker::query()->create([
                'training_program_id' => $program->id,
                'name' => $name,
                'email' => null,
                'is_active' => true,
                'access_version' => 0,
            ]);

            return $this->issueLink($checker, $actor, 'program_attendance_checker.created');
        });
    }

    /**
     * @return array{checker: ProgramAttendanceChecker, token: string, url: string}
     */
    public function regenerateLink(ProgramAttendanceChecker $checker, ?User $actor = null): array
    {
        $checker->loadMissing('trainingProgram');
        $program = $checker->trainingProgram;

        if ($program === null) {
            throw ValidationException::withMessages([
                'name' => 'البرنامج المرتبط بمسؤول التحضير غير موجود.',
            ]);
        }

        $this->assertHasPrepDays($program);

        if (! $checker->is_active) {
            throw ValidationException::withMessages([
                'name' => 'مسؤول التحضير معطّل. فعّله أولاً ثم أنشئ رابطاً جديداً.',
            ]);
        }

        return DB::transaction(function () use ($checker, $actor): array {
            return $this->issueLink($checker, $actor, 'program_attendance_checker.link_regenerated');
        });
    }

    public function setActive(ProgramAttendanceChecker $checker, bool $active, ?User $actor = null): ProgramAttendanceChecker
    {
        $checker->forceFill([
            'is_active' => $active,
            // Bump version so existing sessions die immediately on deactivate.
            'access_version' => $active
                ? (int) $checker->access_version
                : ((int) $checker->access_version) + 1,
        ])->save();

        $this->auditLogger->record(
            $actor ?? (Auth::user() instanceof User ? Auth::user() : null),
            $active
                ? 'program_attendance_checker.activated'
                : 'program_attendance_checker.deactivated',
            AuditLogResult::Success,
            resource: $checker,
            metadata: [
                'training_program_id' => $checker->training_program_id,
                'checker_id' => $checker->id,
                'checker_name' => $checker->name,
                'access_version' => $checker->access_version,
                // Never log tokens.
            ],
        );

        return $checker->fresh() ?? $checker;
    }

    public function findByPlainToken(TrainingProgram $program, string $plainToken): ?ProgramAttendanceChecker
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || strlen($plainToken) < 32) {
            return null;
        }

        $hash = $this->hashToken($plainToken);

        return ProgramAttendanceChecker::query()
            ->where('training_program_id', $program->id)
            ->where('access_token_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    public function touchLastUsed(ProgramAttendanceChecker $checker): void
    {
        $checker->forceFill(['last_used_at' => now()])->save();
    }

    public function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function generatePlainToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function accessUrl(TrainingProgram $program, string $plainToken): string
    {
        return route('gate.access', [
            'program' => $program->slug,
            'token' => $plainToken,
        ], absolute: true);
    }

    /**
     * @return array{checker: ProgramAttendanceChecker, token: string, url: string}
     */
    private function issueLink(
        ProgramAttendanceChecker $checker,
        ?User $actor,
        string $auditAction,
    ): array {
        $checker->loadMissing('trainingProgram');
        $program = $checker->trainingProgram;

        if ($program === null) {
            throw ValidationException::withMessages([
                'name' => 'البرنامج المرتبط بمسؤول التحضير غير موجود.',
            ]);
        }

        $plainToken = $this->generatePlainToken();
        $nextVersion = ((int) $checker->access_version) + 1;

        $checker->forceFill([
            'access_token_hash' => $this->hashToken($plainToken),
            'access_version' => $nextVersion,
            // Clear legacy invite fields so email OTP cannot be reused.
            'invite_code_hash' => null,
            'invite_code_expires_at' => null,
            'invite_attempts' => 0,
        ])->save();

        $this->auditLogger->record(
            $actor ?? (Auth::user() instanceof User ? Auth::user() : null),
            $auditAction,
            AuditLogResult::Success,
            resource: $checker,
            metadata: [
                'training_program_id' => $program->id,
                'checker_id' => $checker->id,
                'checker_name' => $checker->name,
                'access_version' => $nextVersion,
                // Never log the raw token or URL.
            ],
        );

        return [
            'checker' => $checker->fresh() ?? $checker,
            'token' => $plainToken,
            'url' => $this->accessUrl($program, $plainToken),
        ];
    }

    private function assertHasPrepDays(TrainingProgram $program): void
    {
        if (! $program->prepDays()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'أضف أيام البرنامج أولاً قبل إضافة مسؤولي التحضير.',
            ]);
        }
    }
}

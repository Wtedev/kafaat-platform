<?php

namespace App\Services;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(
        private readonly CertificateService $certificateService,
    ) {}

    /**
     * Average progress across published programs in the path (0–100).
     * Programs without a registration for the user count as 0%.
     */
    public function calculatePathProgress(User $user, LearningPath $path): float
    {
        $programs = $this->publishedProgramsInPath($path);

        if ($programs->isEmpty()) {
            return 0.0;
        }

        $total = $programs->reduce(
            fn (float $carry, TrainingProgram $program) => $carry + $this->programProgressPercentage($user, $program),
            0.0
        );

        return round($total / $programs->count(), 2);
    }

    public function getCompletedProgramsCount(User $user, LearningPath $path): int
    {
        $programs = $this->publishedProgramsInPath($path);

        if ($programs->isEmpty()) {
            return 0;
        }

        $completed = 0;

        foreach ($programs as $program) {
            $reg = $this->registrationForProgram($user, $program);
            if ($reg?->status === RegistrationStatus::Completed) {
                $completed++;
            }
        }

        return $completed;
    }

    public function getTotalProgramsInPathCount(LearningPath $path): int
    {
        return $this->publishedProgramsInPath($path)->count();
    }

    /**
     * True when every published program in the path has a completed registration for the user.
     */
    public function isPathCompleted(User $user, LearningPath $path): bool
    {
        $programs = $this->publishedProgramsInPath($path);

        if ($programs->isEmpty()) {
            return false;
        }

        foreach ($programs as $program) {
            $reg = $this->registrationForProgram($user, $program);
            if ($reg === null || $reg->status !== RegistrationStatus::Completed) {
                return false;
            }
        }

        return true;
    }

    /**
     * When all path programs are completed for the user: path registration → Completed, issue path certificate.
     */
    public function completePathIfEligible(User $user, LearningPath $path): void
    {
        if (! $this->isPathCompleted($user, $path)) {
            return;
        }

        $registration = PathRegistration::where('user_id', $user->id)
            ->where('learning_path_id', $path->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->first();

        if ($registration === null) {
            return;
        }

        DB::transaction(function () use ($registration, $user, $path) {
            if ($registration->status !== RegistrationStatus::Completed) {
                $registration->update([
                    'status' => RegistrationStatus::Completed,
                    'completed_at' => now(),
                ]);
            }

            $this->certificateService->issue($user, $path);
        });
    }

    /**
     * Progress for a single program from the user's registration (0–100).
     */
    public function programProgressPercentage(User $user, TrainingProgram $program): float
    {
        $reg = $this->registrationForProgram($user, $program);

        if ($reg === null) {
            return 0.0;
        }

        return match ($reg->status) {
            RegistrationStatus::Completed => 100.0,
            RegistrationStatus::Approved => $this->approvedProgramProgress($reg),
            RegistrationStatus::Pending => 5.0,
            default => 0.0,
        };
    }

    private function approvedProgramProgress(ProgramRegistration $reg): float
    {
        if ($reg->attendance_percentage !== null) {
            return min(99.0, max(0.0, (float) $reg->attendance_percentage));
        }

        return 25.0;
    }

    private function registrationForProgram(User $user, TrainingProgram $program): ?ProgramRegistration
    {
        return ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->where('training_program_id', $program->id)
            ->first();
    }

    /**
     * @return Collection<int, TrainingProgram>
     */
    private function publishedProgramsInPath(LearningPath $path): Collection
    {
        return $path->programs()
            ->where('status', ProgramStatus::Published->value)
            ->orderByRaw('path_sort_order IS NULL')
            ->orderBy('path_sort_order')
            ->orderBy('id')
            ->get();
    }
}

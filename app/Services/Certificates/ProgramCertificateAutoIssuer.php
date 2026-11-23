<?php

namespace App\Services\Certificates;

use App\Enums\SurveyType;
use App\Jobs\IssueProgramCertificateJob;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Support\PublicDiskPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Issues a program certificate (PDF + email) as soon as a registration meets the
 * criteria staff saved in the program's certificates tab. Uses the same
 * eligibility rules and the same issuing job as the manual «إصدار الشهادات».
 */
final class ProgramCertificateAutoIssuer
{
    public function __construct(
        private readonly ProgramCertificateEligibility $eligibility,
    ) {}

    /**
     * Re-check one registration after something it did (attendance, survey).
     */
    public function checkRegistration(int $registrationId): bool
    {
        $registration = ProgramRegistration::query()
            ->with('trainingProgram')
            ->find($registrationId);

        $program = $registration?->trainingProgram;
        if ($program === null || ! $this->isActive($program)) {
            return false;
        }

        [$links, $surveys] = $this->criteria($program);

        $eligible = $this->eligibility->query($program, $links, $surveys)
            ->whereKey($registration->getKey())
            ->exists();

        if (! $eligible) {
            return false;
        }

        $this->dispatch($registration->getKey(), $program);

        return true;
    }

    /**
     * Issue to everyone currently eligible in the program. Returns how many were queued.
     */
    public function sweep(TrainingProgram $program): int
    {
        if (! $this->isActive($program)) {
            return 0;
        }

        [$links, $surveys] = $this->criteria($program);

        $ids = $this->eligibility->query($program, $links, $surveys)
            ->orderBy('program_registrations.id')
            ->pluck('program_registrations.id');

        foreach ($ids as $id) {
            $this->dispatch((int) $id, $program);
        }

        return $ids->count();
    }

    public function sweepAll(): int
    {
        $count = 0;

        TrainingProgram::query()
            ->where('certificate_auto_issue', true)
            ->each(function (TrainingProgram $program) use (&$count): void {
                $count += $this->sweep($program);
            });

        return $count;
    }

    /**
     * @return array{0: list<int>, 1: list<SurveyType>}
     */
    public function criteria(TrainingProgram $program): array
    {
        $raw = is_array($program->certificate_auto_criteria) ? $program->certificate_auto_criteria : [];

        $links = array_values(array_unique(array_map(intval(...), (array) ($raw['attendance_link_ids'] ?? []))));
        $surveys = array_values(array_filter(array_map(
            fn ($value): ?SurveyType => SurveyType::tryFrom((string) $value),
            (array) ($raw['survey_types'] ?? []),
        )));

        return [$links, $surveys];
    }

    /**
     * Auto issuing needs the switch on, a saved design, and at least one criterion
     * (so an empty setting never issues to every accepted registrant).
     */
    public function isActive(TrainingProgram $program): bool
    {
        if (! $program->certificate_auto_issue || $program->trashed()) {
            return false;
        }

        [$links, $surveys] = $this->criteria($program);
        if ($links === [] && $surveys === []) {
            return false;
        }

        if (! $this->backgroundExists($program)) {
            Log::warning('Certificate auto-issue skipped: program has no saved certificate design.', [
                'training_program_id' => $program->getKey(),
            ]);

            return false;
        }

        return true;
    }

    private function backgroundExists(TrainingProgram $program): bool
    {
        $program->loadMissing('certificateTemplate');
        $relative = PublicDiskPath::normalize($program->certificateTemplate?->background_path);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return false;
        }

        return Storage::disk('public')->exists($relative);
    }

    /**
     * Same queue choice as the certificates tab: local public disk lives on the web
     * volume, so those jobs run on the dedicated `certificates` queue.
     */
    private function dispatch(int $registrationId, TrainingProgram $program): void
    {
        $actorId = $program->certificate_auto_enabled_by !== null ? (int) $program->certificate_auto_enabled_by : null;
        $job = new IssueProgramCertificateJob($registrationId, $actorId);

        if (config('filesystems.disks.public.driver') === 'local') {
            dispatch($job->onQueue('certificates'));

            return;
        }

        dispatch($job);
    }
}

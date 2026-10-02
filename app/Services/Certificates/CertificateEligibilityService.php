<?php

namespace App\Services\Certificates;

use App\Data\Certificates\EligibilityResult;
use App\Data\Certificates\EligibilityRules;
use App\Enums\CertificateEligibilityMode;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Models\CertificateTemplate;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\Certificates\Contracts\CertificateEligibilityEvaluator;
use App\Services\ProgramAttendanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CertificateEligibilityService implements CertificateEligibilityEvaluator
{
    public function __construct(
        private readonly ProgramAttendanceService $attendance,
    ) {}

    public function supports(object $registration): bool
    {
        return $registration instanceof ProgramRegistration
            || $registration instanceof PathRegistration
            || $registration instanceof VolunteerRegistration;
    }

    public function evaluate(object $registration): EligibilityResult
    {
        return $this->evaluateMany(collect([$registration]))->get($registration->getKey())
            ?? EligibilityResult::notConfigured();
    }

    /**
     * يستخدم قواعد القالب إن وُجد، وإلا القواعد الاحتياطية (لمسارات بلا قالب بعد).
     */
    public function evaluateRegistration(object $registration, ?EligibilityRules $fallbackRules = null): EligibilityResult
    {
        if (! $this->supports($registration)) {
            throw new InvalidArgumentException('تقييم الأحقية يدعم تسجيلات البرامج والمسارات والتطوع فقط.');
        }

        $owner = $this->ownerOf($registration);
        $template = $owner?->certificateTemplate;
        if ($template instanceof CertificateTemplate) {
            return $this->evaluate($registration);
        }

        if ($fallbackRules === null) {
            return EligibilityResult::notConfigured();
        }

        return $this->evaluateGroup(collect([$registration]), $fallbackRules)->get($registration->getKey())
            ?? EligibilityResult::notConfigured();
    }

    public function evaluateMany(Collection $registrations): Collection
    {
        if ($registrations->isEmpty()) {
            return collect();
        }

        $registrations->each(function (object $registration): void {
            if (! $this->supports($registration)) {
                throw new InvalidArgumentException('تقييم الأحقية يدعم تسجيلات البرامج والمسارات والتطوع فقط.');
            }
        });

        $results = collect();
        foreach ($registrations->groupBy(fn (object $registration): string => $registration::class) as $group) {
            $results = $results->union($this->evaluateGroup($group));
        }

        return $results;
    }

    /**
     * تسمية عمود التقدّم في تبويب الشهادات، باستعلام ثابت لكل صفحة.
     *
     * @param  Collection<int, Model>  $registrations
     * @return array<int, string>
     */
    public function progressLabels(Collection $registrations): array
    {
        if ($registrations->isEmpty()) {
            return [];
        }

        $first = $registrations->first();
        if ($first instanceof PathRegistration) {
            $progress = $this->pathCourseProgress($registrations);

            return $registrations->mapWithKeys(function (PathRegistration $registration) use ($progress): array {
                $pathId = (int) $registration->learning_path_id;
                $done = $progress['completed'][$pathId][(int) $registration->user_id] ?? 0;
                $total = $progress['totals'][$pathId] ?? 0;

                return [(int) $registration->getKey() => $done.' / '.$total];
            })->all();
        }

        if ($first instanceof VolunteerRegistration) {
            $hours = $this->approvedHours($registrations);

            return $registrations->mapWithKeys(function (VolunteerRegistration $registration) use ($hours): array {
                $key = (int) $registration->user_id.'-'.(int) $registration->opportunity_id;
                $value = $hours[$key] ?? 0.0;

                return [(int) $registration->getKey() => $this->formatNumber($value)];
            })->all();
        }

        return [];
    }

    /**
     * @param  Collection<int, Model>  $registrations
     * @return Collection<int|string, EligibilityResult>
     */
    private function evaluateGroup(Collection $registrations, ?EligibilityRules $forcedRules = null): Collection
    {
        $first = $registrations->first();

        if ($first instanceof ProgramRegistration) {
            return $this->evaluatePrograms($registrations, $forcedRules);
        }

        if ($first instanceof PathRegistration) {
            return $this->evaluatePaths($registrations, $forcedRules);
        }

        if ($first instanceof VolunteerRegistration) {
            return $this->evaluateVolunteers($registrations, $forcedRules);
        }

        return collect();
    }

    /**
     * @param  Collection<int, ProgramRegistration>  $registrations
     * @return Collection<int|string, EligibilityResult>
     */
    private function evaluatePrograms(Collection $registrations, ?EligibilityRules $forcedRules): Collection
    {
        $programIds = $registrations->pluck('training_program_id')->filter()->unique()->values();
        $templates = $forcedRules === null
            ? $this->templatesFor(new TrainingProgram, $programIds)
            : collect();
        $percentages = $this->attendance->percentagesForRegistrations($registrations);
        $programs = $this->programsNeededForEndDate($templates, $programIds, $forcedRules);

        return $registrations->mapWithKeys(function (ProgramRegistration $registration) use ($templates, $percentages, $programs, $forcedRules): array {
            $rules = $forcedRules ?? $templates->get($registration->training_program_id)?->eligibility;
            if (! $rules instanceof EligibilityRules) {
                return [$registration->getKey() => EligibilityResult::notConfigured()];
            }

            return [$registration->getKey() => $this->evaluateMetrics(
                $registration->status,
                $rules,
                $percentages[$registration->getKey()] ?? null,
                $registration->score !== null ? (float) $registration->score : null,
                $programs->get($registration->training_program_id)?->end_date,
            )];
        });
    }

    /**
     * @param  Collection<int, PathRegistration>  $registrations
     * @return Collection<int|string, EligibilityResult>
     */
    private function evaluatePaths(Collection $registrations, ?EligibilityRules $forcedRules): Collection
    {
        $pathIds = $registrations->pluck('learning_path_id')->filter()->unique()->values();
        $templates = $forcedRules === null
            ? $this->templatesFor(new LearningPath, $pathIds)
            : collect();
        $needsCourses = $forcedRules?->mode === CertificateEligibilityMode::CompletedAllCourses
            || $templates->contains(fn (CertificateTemplate $template): bool => $template->eligibility->mode === CertificateEligibilityMode::CompletedAllCourses);
        $progress = $needsCourses ? $this->pathCourseProgress($registrations) : ['totals' => [], 'completed' => []];

        return $registrations->mapWithKeys(function (PathRegistration $registration) use ($templates, $forcedRules, $progress): array {
            $rules = $forcedRules ?? $templates->get($registration->learning_path_id)?->eligibility;
            if (! $rules instanceof EligibilityRules) {
                return [$registration->getKey() => EligibilityResult::notConfigured()];
            }

            if ($rules->mode === CertificateEligibilityMode::CompletedAllCourses) {
                return [$registration->getKey() => $this->evaluateCompletedCourses($registration, $rules, $progress)];
            }

            return [$registration->getKey() => $this->evaluateMetrics(
                $registration->status,
                $rules,
                $registration->attendance_percentage !== null ? (float) $registration->attendance_percentage : null,
                $registration->score !== null ? (float) $registration->score : null,
                null,
            )];
        });
    }

    /**
     * @param  Collection<int, VolunteerRegistration>  $registrations
     * @return Collection<int|string, EligibilityResult>
     */
    private function evaluateVolunteers(Collection $registrations, ?EligibilityRules $forcedRules): Collection
    {
        $opportunityIds = $registrations->pluck('opportunity_id')->filter()->unique()->values();
        $templates = $forcedRules === null
            ? $this->templatesFor(new VolunteerOpportunity, $opportunityIds)
            : collect();
        $hours = $this->approvedHours($registrations);
        $needsEnd = $forcedRules?->requireActivityEnded === true
            || $templates->contains(fn (CertificateTemplate $template): bool => $template->eligibility->requireActivityEnded);
        $opportunities = $needsEnd
            ? VolunteerOpportunity::query()->whereIn('id', $opportunityIds)->get(['id', 'end_date'])->keyBy('id')
            : collect();

        return $registrations->mapWithKeys(function (VolunteerRegistration $registration) use ($templates, $forcedRules, $hours, $opportunities): array {
            $rules = $forcedRules ?? $templates->get($registration->opportunity_id)?->eligibility;
            if (! $rules instanceof EligibilityRules) {
                return [$registration->getKey() => EligibilityResult::notConfigured()];
            }

            $statusResult = $this->statusAndEnd(
                $registration->status,
                $rules,
                $opportunities->get($registration->opportunity_id)?->end_date,
            );
            if ($statusResult !== null) {
                return [$registration->getKey() => $statusResult];
            }

            if ($rules->mode !== CertificateEligibilityMode::MinApprovedHours) {
                return [$registration->getKey() => EligibilityResult::notEligible(['نمط الأحقية لا يناسب الفرصة التطوعية'])];
            }

            if ($rules->minApprovedHours === null) {
                return [$registration->getKey() => EligibilityResult::awaitingData(['لم يُحدد حد الساعات'])];
            }

            $key = (int) $registration->user_id.'-'.(int) $registration->opportunity_id;
            $approved = round($hours[$key] ?? 0.0, 2);
            $limit = (float) $rules->minApprovedHours;
            if ($approved < $limit) {
                return [$registration->getKey() => EligibilityResult::notEligible([
                    'ساعاتك المعتمدة '.$this->formatNumber($approved).' أقل من الحد '.$this->formatNumber($limit),
                ])];
            }

            return [$registration->getKey() => EligibilityResult::eligible()];
        });
    }

    /**
     * @param  array{totals: array<int, int>, completed: array<int, array<int, int>>}  $progress
     */
    private function evaluateCompletedCourses(PathRegistration $registration, EligibilityRules $rules, array $progress): EligibilityResult
    {
        $statusResult = $this->statusAndEnd($registration->status, $rules, null);
        if ($statusResult !== null) {
            return $statusResult;
        }

        $pathId = (int) $registration->learning_path_id;
        $total = $progress['totals'][$pathId] ?? 0;
        $done = $progress['completed'][$pathId][(int) $registration->user_id] ?? 0;

        if ($total === 0) {
            return EligibilityResult::notEligible(['لا توجد دورات منشورة في المسار']);
        }

        if ($done < $total) {
            return EligibilityResult::notEligible(['أكملت '.$done.' من '.$total.' دورات']);
        }

        return EligibilityResult::eligible();
    }

    private function evaluateMetrics(
        RegistrationStatus|string|null $status,
        EligibilityRules $rules,
        ?float $attendance,
        ?float $score,
        mixed $endDate,
    ): EligibilityResult {
        $statusResult = $this->statusAndEnd($status, $rules, $endDate);
        if ($statusResult !== null) {
            return $statusResult;
        }

        $missing = [];
        $failures = [];

        $needsAttendance = in_array($rules->mode, [
            CertificateEligibilityMode::AttendanceOnly,
            CertificateEligibilityMode::Both,
            CertificateEligibilityMode::Average,
        ], true);
        $needsScore = in_array($rules->mode, [
            CertificateEligibilityMode::ScoreOnly,
            CertificateEligibilityMode::Both,
            CertificateEligibilityMode::Average,
        ], true);

        if ($needsAttendance && $attendance === null) {
            $missing[] = 'لم يُرصد الحضور بعد';
        }

        if ($needsScore && $score === null) {
            $missing[] = 'لم تُرصد الدرجة بعد';
        }

        if ($missing !== []) {
            return EligibilityResult::awaitingData($missing);
        }

        if ($rules->mode === CertificateEligibilityMode::Average) {
            $average = round(($attendance + $score) / 2, 2);
            if ($average < (float) $rules->minAverage) {
                $failures[] = 'المتوسط '.$this->formatNumber($average).'% أقل من الحد '.$this->formatNumber((float) $rules->minAverage).'%';
            }
        } else {
            if ($needsAttendance && $attendance < (float) $rules->minAttendance) {
                $failures[] = 'الحضور '.$this->formatNumber($attendance).'% أقل من الحد '.$this->formatNumber((float) $rules->minAttendance).'%';
            }

            if ($needsScore && $score < (float) $rules->minScore) {
                $failures[] = 'الدرجة '.$this->formatNumber($score).' أقل من الحد '.$this->formatNumber((float) $rules->minScore);
            }
        }

        if ($failures !== []) {
            return EligibilityResult::notEligible($failures);
        }

        return EligibilityResult::eligible();
    }

    private function statusAndEnd(RegistrationStatus|string|null $status, EligibilityRules $rules, mixed $endDate): ?EligibilityResult
    {
        if ($rules->requireCompletedStatus && ! in_array($status, [
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true)) {
            return EligibilityResult::notEligible(['التسجيل ليس مقبولاً أو مكتملاً']);
        }

        if (! $rules->requireActivityEnded) {
            return null;
        }

        if ($endDate === null) {
            return EligibilityResult::awaitingData(['لم يُحدد تاريخ نهاية النشاط']);
        }

        if (Carbon::parse($endDate)->endOfDay()->isFuture()) {
            return EligibilityResult::notEligible(['لم ينتهِ النشاط بعد']);
        }

        return null;
    }

    /**
     * @param  Collection<int, mixed>  $ownerIds
     * @return Collection<int|string, CertificateTemplate>
     */
    private function templatesFor(Model $owner, Collection $ownerIds): Collection
    {
        if ($ownerIds->isEmpty()) {
            return collect();
        }

        return CertificateTemplate::query()
            ->where('owner_type', $owner->getMorphClass())
            ->whereIn('owner_id', $ownerIds)
            ->get()
            ->keyBy(fn (CertificateTemplate $template): int => (int) $template->owner_id);
    }

    /**
     * @param  Collection<int, PathRegistration>  $registrations
     * @return array{totals: array<int, int>, completed: array<int, array<int, int>>}
     */
    private function pathCourseProgress(Collection $registrations): array
    {
        $pathIds = $registrations->pluck('learning_path_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $userIds = $registrations->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->values();
        if ($pathIds->isEmpty()) {
            return ['totals' => [], 'completed' => []];
        }

        $programs = TrainingProgram::query()
            ->whereIn('learning_path_id', $pathIds)
            ->where('status', ProgramStatus::Published)
            ->get(['id', 'learning_path_id']);

        $totals = [];
        $programsByPath = [];
        foreach ($programs as $program) {
            $pathId = (int) $program->learning_path_id;
            $totals[$pathId] = ($totals[$pathId] ?? 0) + 1;
            $programsByPath[$pathId][] = (int) $program->id;
        }

        $completed = [];
        if ($programs->isNotEmpty() && $userIds->isNotEmpty()) {
            $rows = ProgramRegistration::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('training_program_id', $programs->pluck('id'))
                ->where('status', RegistrationStatus::Completed->value)
                ->get(['user_id', 'training_program_id']);

            $programPath = [];
            foreach ($programsByPath as $pathId => $ids) {
                foreach ($ids as $programId) {
                    $programPath[$programId] = $pathId;
                }
            }

            foreach ($rows as $row) {
                $pathId = $programPath[(int) $row->training_program_id] ?? null;
                if ($pathId === null) {
                    continue;
                }
                $userId = (int) $row->user_id;
                $completed[$pathId][$userId] = ($completed[$pathId][$userId] ?? 0) + 1;
            }
        }

        return ['totals' => $totals, 'completed' => $completed];
    }

    /**
     * @param  Collection<int, VolunteerRegistration>  $registrations
     * @return array<string, float>
     */
    private function approvedHours(Collection $registrations): array
    {
        $userIds = $registrations->pluck('user_id')->filter()->unique()->values();
        $opportunityIds = $registrations->pluck('opportunity_id')->filter()->unique()->values();
        if ($userIds->isEmpty() || $opportunityIds->isEmpty()) {
            return [];
        }

        $rows = VolunteerHour::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('opportunity_id', $opportunityIds)
            ->where('status', VolunteerHoursStatus::Approved->value)
            ->groupBy('user_id', 'opportunity_id')
            ->selectRaw('user_id, opportunity_id, SUM(hours) as total')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->user_id.'-'.(int) $row->opportunity_id] = round((float) $row->total, 2);
        }

        return $map;
    }

    /**
     * @param  Collection<int|string, CertificateTemplate>  $templates
     * @param  Collection<int, mixed>  $programIds
     * @return Collection<int|string, TrainingProgram>
     */
    private function programsNeededForEndDate(Collection $templates, Collection $programIds, ?EligibilityRules $forcedRules): Collection
    {
        $needsEndDate = $forcedRules?->requireActivityEnded === true
            || $templates->contains(fn (CertificateTemplate $template): bool => $template->eligibility->requireActivityEnded);

        if (! $needsEndDate || $programIds->isEmpty()) {
            return collect();
        }

        return TrainingProgram::query()
            ->whereIn('id', $programIds)
            ->get(['id', 'end_date'])
            ->keyBy(fn (TrainingProgram $program): int => (int) $program->id);
    }

    private function ownerOf(object $registration): ?Model
    {
        if ($registration instanceof ProgramRegistration) {
            $registration->loadMissing('trainingProgram');

            return $registration->trainingProgram;
        }

        if ($registration instanceof PathRegistration) {
            $registration->loadMissing('learningPath');

            return $registration->learningPath;
        }

        if ($registration instanceof VolunteerRegistration) {
            $registration->loadMissing('opportunity');

            return $registration->opportunity;
        }

        return null;
    }

    private function formatNumber(float $value): string
    {
        $rounded = round($value, 2);

        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return number_format($rounded, 2, '.', '');
    }
}

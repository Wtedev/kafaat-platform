<?php

namespace App\Services\Certificates;

use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\TrainingProgram;
use App\Services\Surveys\ProgramSurveyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProgramCertificateEligibility
{
    public function __construct(
        private readonly ProgramSurveyService $surveys,
    ) {}

    /**
     * Approved or completed registrations that do not already have a certificate.
     * An empty attendance list and an empty survey list apply no extra criteria.
     *
     * @param  list<int>  $attendanceLinkIds
     * @param  list<SurveyType>  $requiredSurveys
     * @return Collection<int, ProgramRegistration>
     */
    public function eligible(TrainingProgram $program, array $attendanceLinkIds = [], array $requiredSurveys = []): Collection
    {
        return $this->query($program, $attendanceLinkIds, $requiredSurveys)
            ->with('user')
            ->orderBy('program_registrations.id')
            ->get();
    }

    /**
     * @param  list<int>  $attendanceLinkIds
     * @param  list<SurveyType>  $requiredSurveys
     */
    public function count(TrainingProgram $program, array $attendanceLinkIds = [], array $requiredSurveys = []): int
    {
        return $this->query($program, $attendanceLinkIds, $requiredSurveys)->count();
    }

    /**
     * @param  list<int>  $attendanceLinkIds
     * @param  list<SurveyType>  $requiredSurveys
     * @return Builder<ProgramRegistration>
     */
    public function query(TrainingProgram $program, array $attendanceLinkIds = [], array $requiredSurveys = []): Builder
    {
        $query = ProgramRegistration::query()
            ->where('program_registrations.training_program_id', $program->getKey())
            ->whereIn('program_registrations.status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->whereNotExists(function ($certificate) use ($program): void {
                $certificate->selectRaw('1')
                    ->from('certificates')
                    ->whereColumn('certificates.user_id', 'program_registrations.user_id')
                    ->where('certificates.certificateable_type', $program->getMorphClass())
                    ->where('certificates.certificateable_id', $program->getKey());
            });

        $this->requireAttendance($query, $program, $attendanceLinkIds);
        $this->requireSurveys($query, $program, $requiredSurveys);

        return $query;
    }

    /**
     * @param  Builder<ProgramRegistration>  $query
     * @param  list<int>  $attendanceLinkIds
     */
    private function requireAttendance(Builder $query, TrainingProgram $program, array $attendanceLinkIds): void
    {
        $requested = array_values(array_unique(array_map(intval(...), $attendanceLinkIds)));
        if ($requested === []) {
            return;
        }

        $resolved = ProgramAttendanceLink::query()
            ->where('training_program_id', $program->getKey())
            ->whereNull('cancelled_at')
            ->whereIn('id', $requested)
            ->pluck('id');

        if ($resolved->count() !== count($requested)) {
            $query->whereRaw('0 = 1');

            return;
        }

        foreach ($resolved as $linkId) {
            $query->whereExists(function ($mark) use ($linkId): void {
                $mark->selectRaw('1')
                    ->from('program_attendance_marks')
                    ->whereColumn('program_attendance_marks.program_registration_id', 'program_registrations.id')
                    ->where('program_attendance_marks.program_attendance_link_id', $linkId);
            });
        }
    }

    /**
     * @param  Builder<ProgramRegistration>  $query
     * @param  list<SurveyType>  $requiredSurveys
     */
    private function requireSurveys(Builder $query, TrainingProgram $program, array $requiredSurveys): void
    {
        $wanted = collect($requiredSurveys)
            ->map(fn (SurveyType $type): string => $type->value)
            ->unique()
            ->values();

        if ($wanted->isEmpty()) {
            return;
        }

        $surveys = ProgramSurvey::query()
            ->where('training_program_id', $program->getKey())
            ->whereIn('type', $wanted->all())
            ->get()
            ->keyBy(fn (ProgramSurvey $survey): string => $survey->type->value);

        if ($surveys->count() !== $wanted->count()) {
            $query->whereRaw('0 = 1');

            return;
        }

        foreach ($wanted as $type) {
            $this->surveys->whereRegistrationAnswered($query, $surveys->get($type));
        }
    }
}

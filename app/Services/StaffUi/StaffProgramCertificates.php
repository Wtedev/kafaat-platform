<?php

namespace App\Services\StaffUi;

use App\Enums\RegistrationStatus;
use App\Enums\SurveyType;
use App\Models\Certificate;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\TrainingProgram;
use App\Support\ArabicText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The certificates tab: one row per accepted trainee with their check-in time on
 * every attendance day, their pre / post / satisfaction surveys, and their certificate.
 */
final class StaffProgramCertificates
{
    public const SURVEY_COLUMNS = [
        'pre' => 'استبيان قبلي',
        'post' => 'استبيان بعدي',
        'satisfaction' => 'استبيان الرضا',
    ];

    /**
     * @return Builder<ProgramRegistration>
     */
    public function rows(TrainingProgram $program, string $search = ''): Builder
    {
        $query = ProgramRegistration::query()
            ->where('training_program_id', $program->getKey())
            ->whereIn('program_registrations.status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value])
            ->join('users', 'users.id', '=', 'program_registrations.user_id')
            ->orderBy('users.name')
            ->select('program_registrations.*')
            ->with('user:id,name');

        $search = trim($search);
        if ($search !== '') {
            $like = '%'.ArabicText::fold($search).'%';
            $query->where(function (Builder $match) use ($like): void {
                foreach (['users.name', 'users.first_name', 'users.father_name', 'users.grandfather_name', 'users.family_name'] as $column) {
                    $match->orWhereRaw(ArabicText::sqlFoldColumn($column).' LIKE ?', [$like]);
                }
            });
        }

        return $query;
    }

    /**
     * Attendance days in the order they were added.
     *
     * @return Collection<int, ProgramAttendanceLink>
     */
    public function days(TrainingProgram $program): Collection
    {
        return $program->attendanceLinks()->whereNull('cancelled_at')->orderBy('id')->get();
    }

    /**
     * Check-in time per registration and day: [registration_id][link_id] => Carbon.
     *
     * @param  list<int>  $registrationIds
     * @return array<int, array<int, Carbon>>
     */
    public function checkIns(Collection $days, array $registrationIds): array
    {
        if ($days->isEmpty() || $registrationIds === []) {
            return [];
        }

        $out = [];
        ProgramAttendanceMark::query()
            ->whereIn('program_attendance_link_id', $days->modelKeys())
            ->whereIn('program_registration_id', $registrationIds)
            ->get(['program_attendance_link_id', 'program_registration_id', 'attended_at'])
            ->each(function (ProgramAttendanceMark $mark) use (&$out): void {
                $out[(int) $mark->program_registration_id][(int) $mark->program_attendance_link_id] = $mark->attended_at;
            });

        return $out;
    }

    /**
     * The program's survey of each type, if it exists.
     *
     * @return array<string, ProgramSurvey|null>
     */
    public function surveys(TrainingProgram $program): array
    {
        $surveys = ProgramSurvey::query()->where('training_program_id', $program->getKey())->get()->keyBy(fn (ProgramSurvey $s) => $s->type?->value);

        return collect(array_keys(self::SURVEY_COLUMNS))
            ->mapWithKeys(fn (string $type) => [$type => $surveys->get($type)])
            ->all();
    }

    /**
     * When each registration answered each survey: [registration_id][type] => Carbon|true.
     * Answers saved before completion times were kept show as true.
     *
     * @param  array<string, ProgramSurvey|null>  $surveys
     * @param  list<int>  $registrationIds
     * @return array<int, array<string, Carbon|true>>
     */
    public function surveyAnswers(array $surveys, array $registrationIds): array
    {
        $byId = collect($surveys)->filter()->mapWithKeys(fn (ProgramSurvey $s, string $type) => [$s->getKey() => $type]);
        if ($byId->isEmpty() || $registrationIds === []) {
            return [];
        }

        $out = [];
        // Named answers keep their time on the response; anonymous ones on the completion row.
        foreach (['survey_responses' => 'created_at', 'survey_completions' => 'completed_at'] as $table => $column) {
            DB::table($table)
                ->whereIn('program_survey_id', $byId->keys()->all())
                ->whereIn('registration_id', $registrationIds)
                ->get(['program_survey_id', 'registration_id', $column.' as answered_at'])
                ->each(function (object $row) use (&$out, $byId): void {
                    $type = $byId[(int) $row->program_survey_id] ?? null;
                    if ($type === null) {
                        return;
                    }
                    $out[(int) $row->registration_id][$type] ??= $row->answered_at !== null
                        ? Carbon::parse($row->answered_at)
                        : true;
                });
        }

        return $out;
    }

    /**
     * Issued certificates keyed by user id.
     *
     * @return Collection<int, Certificate>
     */
    public function certificates(TrainingProgram $program): Collection
    {
        return Certificate::query()
            ->where('certificateable_type', $program->getMorphClass())
            ->where('certificateable_id', $program->getKey())
            ->get()
            ->keyBy('user_id');
    }

    public function backgroundReady(TrainingProgram $program): bool
    {
        $path = $program->certificateTemplate()->value('background_path');

        return filled($path) && Storage::disk('public')->exists((string) $path);
    }

    public static function surveyType(string $key): ?SurveyType
    {
        return SurveyType::tryFrom($key);
    }
}

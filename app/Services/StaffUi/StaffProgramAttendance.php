<?php

namespace App\Services\StaffUi;

use App\Enums\AttendanceStatus;
use App\Enums\ProgramPrepDayType;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\Identity\IdentityNumberService;
use App\Support\ArabicText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The attendance tab: program days (in-person QR / remote), generating days
 * from the schedule, and the per-day manual roster.
 */
final class StaffProgramAttendance
{
    /** Never generate more days than this in one go. */
    private const MAX_GENERATED_DAYS = 120;

    /**
     * Days with how many approved registrants attended each.
     *
     * @return Collection<int, array{day: ProgramPrepDay, present: int}>
     */
    public function days(TrainingProgram $program): Collection
    {
        $days = $program->prepDays()->get();
        if ($days->isEmpty()) {
            return collect();
        }

        $present = ProgramAttendance::query()
            ->whereHas('registration', fn (Builder $q) => $q->where('training_program_id', $program->getKey()))
            ->whereIn('status', AttendanceStatus::attendedValues())
            ->selectRaw('training_date, count(*) as aggregate')
            ->groupBy('training_date')
            ->pluck('aggregate', 'training_date')
            ->mapWithKeys(fn ($count, $date) => [Carbon::parse((string) $date)->toDateString() => (int) $count]);

        return $days->map(fn (ProgramPrepDay $day): array => [
            'day' => $day,
            'present' => (int) ($present[$day->dateString()] ?? 0),
        ]);
    }

    public function addDay(TrainingProgram $program, string $date, ProgramPrepDayType $type): ProgramPrepDay
    {
        $date = Carbon::parse($date)->toDateString();

        if ($program->prepDays()->whereDate('prep_date', $date)->exists()) {
            throw ValidationException::withMessages(['prep_date' => 'هذا اليوم مضاف من قبل.']);
        }

        return $program->prepDays()->create([
            'prep_date' => $date,
            'delivery_type' => $type,
            'requires_attendance' => true,
        ]);
    }

    public function changeType(ProgramPrepDay $day, ProgramPrepDayType $type): void
    {
        $day->update(['delivery_type' => $type]);
    }

    /**
     * Days that already have attendance are kept, so no record is lost by accident.
     */
    public function removeDay(TrainingProgram $program, ProgramPrepDay $day): void
    {
        $hasAttendance = ProgramAttendance::query()
            ->whereHas('registration', fn (Builder $q) => $q->where('training_program_id', $program->getKey()))
            ->whereDate('training_date', $day->dateString())
            ->exists();

        if ($hasAttendance) {
            throw ValidationException::withMessages([
                'prep_date' => 'لا يمكن حذف يوم فيه حضور مسجّل. ألغِ التحضير أولاً.',
            ]);
        }

        $day->delete();
    }

    /**
     * Create a day for every program date that is still missing, following the
     * start/end dates and the chosen weekdays.
     *
     * @return int Number of days created.
     */
    public function generateFromSchedule(TrainingProgram $program, ProgramPrepDayType $type): int
    {
        if ($program->start_date === null) {
            throw ValidationException::withMessages(['prep_date' => 'أدخل تاريخ بداية البرنامج أولاً من الإعدادات.']);
        }

        $start = $program->start_date->copy()->startOfDay();
        $end = ($program->program_kind === TrainingProgramKind::Session || $program->end_date === null)
            ? $start->copy()
            : $program->end_date->copy()->startOfDay();
        $weekdays = is_array($program->weekdays) && $program->weekdays !== []
            ? array_map('intval', $program->weekdays)
            : null;

        $existing = $program->prepDays()->get()->map(fn (ProgramPrepDay $d) => $d->dateString())->flip();
        $created = 0;

        for ($day = $start->copy(); $day->lte($end) && $created < self::MAX_GENERATED_DAYS; $day->addDay()) {
            if ($weekdays !== null && ! in_array($day->dayOfWeek, $weekdays, true)) {
                continue;
            }
            if ($existing->has($day->toDateString())) {
                continue;
            }

            $program->prepDays()->create([
                'prep_date' => $day->toDateString(),
                'delivery_type' => $type,
                'requires_attendance' => true,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Approved registrants for the roster, optionally narrowed by a name or an identity number.
     *
     * @return Builder<ProgramRegistration>
     */
    public function roster(TrainingProgram $program, string $search = ''): Builder
    {
        $query = ProgramRegistration::query()
            ->where('training_program_id', $program->getKey())
            ->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value]);

        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        $normalized = IdentityNumberService::normalize($search);
        if (is_string($normalized) && IdentityNumberService::isValidFormat($normalized)) {
            $hash = IdentityNumberService::generateLookupHash($normalized);

            return $query->whereHas('user', fn (Builder $u) => $u->where('identity_number_lookup_hash', $hash));
        }

        $like = '%'.ArabicText::fold($search).'%';

        return $query->whereHas('user', function (Builder $user) use ($like): void {
            $user->where(function (Builder $match) use ($like): void {
                foreach (['name', 'first_name', 'father_name', 'grandfather_name', 'family_name'] as $column) {
                    $match->orWhereRaw(ArabicText::sqlFoldColumn($column).' LIKE ?', [$like]);
                }
            });
        });
    }

    /**
     * Attendance rows of one day, keyed by registration id.
     *
     * @param  list<int>  $registrationIds
     * @return Collection<int, ProgramAttendance>
     */
    public function attendanceOn(string $date, array $registrationIds): Collection
    {
        if ($registrationIds === []) {
            return collect();
        }

        return ProgramAttendance::query()
            ->whereIn('program_registration_id', $registrationIds)
            ->whereDate('training_date', $date)
            ->whereIn('status', AttendanceStatus::attendedValues())
            ->get()
            ->keyBy('program_registration_id');
    }
}

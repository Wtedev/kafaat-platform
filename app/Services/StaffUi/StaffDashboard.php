<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\StaffAlerts\StaffAlerts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The staff home page: the platform's figures for this month, the programs
 * running now, and a summary of what needs attention.
 */
final class StaffDashboard
{
    public function __construct(
        private readonly StaffAlerts $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, ?Carbon $now = null): array
    {
        $now ??= now();
        $local = $now->copy()->timezone(config('app.timezone'));
        $canPrograms = $user->can('viewAny', TrainingProgram::class);

        $this->alerts->refreshIfStale();

        return [
            'alerts' => $this->alerts->visibleFor($user),
            'canPrograms' => $canPrograms,
            'monthLabel' => en_digits(ar_date($local, 'MMMM y')),
            'stats' => $canPrograms ? $this->monthStats($local) : [],
            'running' => $canPrograms ? $this->running($local, $now) : collect(),
        ];
    }

    /**
     * This month so far, next to the whole of last month.
     *
     * @return list<array{icon: string, label: string, value: int, previous: int}>
     */
    private function monthStats(Carbon $local): array
    {
        $start = $local->copy()->startOfMonth()->utc();
        $prevStart = $local->copy()->subMonthNoOverflow()->startOfMonth()->utc();
        $prevEnd = $start;
        $morph = (new TrainingProgram)->getMorphClass();
        $activePrograms = fn ($q) => $q->withoutTrashed();

        $count = function (callable $query) use ($start, $prevStart, $prevEnd): array {
            return [$query($start, null), $query($prevStart, $prevEnd)];
        };

        $between = fn ($q, string $column, Carbon $from, ?Carbon $to) => $q->where($column, '>=', $from)->when($to, fn ($w) => $w->where($column, '<', $to));

        [$users, $usersPrev] = $count(fn ($from, $to) => $between(
            User::query()->whereIn('role_type', ['beneficiary', 'trainee', 'volunteer']), 'created_at', $from, $to)->count());

        [$registrations, $registrationsPrev] = $count(fn ($from, $to) => $between(
            ProgramRegistration::query()->whereHas('trainingProgram', $activePrograms), 'created_at', $from, $to)->count());

        [$accepted, $acceptedPrev] = $count(fn ($from, $to) => $between(
            ProgramRegistration::query()
                ->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value])
                ->whereHas('trainingProgram', $activePrograms), 'approved_at', $from, $to)->count());

        [$attendance, $attendancePrev] = $count(fn ($from, $to) => $between(
            ProgramAttendanceMark::query(), 'attended_at', $from, $to)->count());

        [$surveys, $surveysPrev] = $count(fn ($from, $to) => $between(DB::table('survey_responses')->whereNotNull('registration_id'), 'created_at', $from, $to)->count()
            + $between(DB::table('survey_completions'), 'completed_at', $from, $to)->count());

        [$certificates, $certificatesPrev] = $count(fn ($from, $to) => $between(
            Certificate::query()->where('certificateable_type', $morph), 'issued_at', $from, $to)->count());

        return [
            ['icon' => 'user-round-plus', 'label' => 'مستخدمون جدد', 'value' => $users, 'previous' => $usersPrev],
            ['icon' => 'clipboard-pen', 'label' => 'تسجيلات في البرامج', 'value' => $registrations, 'previous' => $registrationsPrev],
            ['icon' => 'user-check', 'label' => 'طلبات مقبولة', 'value' => $accepted, 'previous' => $acceptedPrev],
            ['icon' => 'scan-line', 'label' => 'حضور مسجّل', 'value' => $attendance, 'previous' => $attendancePrev],
            ['icon' => 'clipboard-list', 'label' => 'استبيانات مُعبّأة', 'value' => $surveys, 'previous' => $surveysPrev],
            ['icon' => 'award', 'label' => 'شهادات صادرة', 'value' => $certificates, 'previous' => $certificatesPrev],
        ];
    }

    /**
     * Published programs taking place today, with their figures and today's attendance.
     *
     * @return Collection<int, TrainingProgram>
     */
    private function running(Carbon $local, Carbon $now): Collection
    {
        $date = $local->toDateString();
        $dayStart = $local->copy()->startOfDay()->utc();

        $programs = TrainingProgram::query()
            ->where('status', ProgramStatus::Published->value)
            ->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereDate('end_date', '>=', $date)
                ->orWhere(fn (Builder $s) => $s->whereNull('end_date')->whereDate('start_date', $date)))
            ->withCount([
                'registrations as accepted_count' => fn ($q) => $q->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value]),
                'registrations as pending_count' => fn ($q) => $q->where('status', RegistrationStatus::Pending->value),
            ])
            ->orderBy('end_date')
            ->get();

        $links = ProgramAttendanceLink::query()
            ->whereIn('training_program_id', $programs->modelKeys())
            ->whereNull('cancelled_at')
            ->where(fn (Builder $q) => $q->where('opens_at', '>=', $dayStart)->orWhere('closes_at', '>', $now))
            ->withCount('marks')
            ->orderByDesc('opens_at')
            ->get()
            ->groupBy('training_program_id');

        return $programs->each(fn (TrainingProgram $p) => $p->setRelation('todayLinks', $links->get($p->id, collect())));
    }
}

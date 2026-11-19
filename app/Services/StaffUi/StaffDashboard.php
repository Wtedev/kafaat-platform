<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramAttendanceMark;
use App\Models\ProgramRegistration;
use App\Models\StaffAlert;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\StaffAlerts\StaffAlerts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            'upcoming' => $canPrograms ? $this->upcoming($local) : collect(),
            'alertSummary' => $this->summarize($this->alerts->visibleFor($user)),
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

        [$certificates, $certificatesPrev] = $count(fn ($from, $to) => $between(
            Certificate::query()->where('certificateable_type', $morph), 'issued_at', $from, $to)->count());

        return [
            ['icon' => 'user-round-plus', 'label' => 'مستخدمون جدد', 'value' => $users, 'previous' => $usersPrev],
            ['icon' => 'clipboard-pen', 'label' => 'تسجيلات في البرامج', 'value' => $registrations, 'previous' => $registrationsPrev],
            ['icon' => 'user-check', 'label' => 'طلبات مقبولة', 'value' => $accepted, 'previous' => $acceptedPrev],
            ['icon' => 'scan-line', 'label' => 'حضور مسجّل', 'value' => $attendance, 'previous' => $attendancePrev],
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

    /**
     * Published programs starting within the next 30 days.
     *
     * @return Collection<int, TrainingProgram>
     */
    private function upcoming(Carbon $local): Collection
    {
        return TrainingProgram::query()
            ->where('status', ProgramStatus::Published->value)
            ->whereDate('start_date', '>', $local->toDateString())
            ->whereDate('start_date', '<=', $local->copy()->addDays(30)->toDateString())
            ->withCount([
                'registrations as accepted_count' => fn ($q) => $q->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value]),
                'registrations as pending_count' => fn ($q) => $q->where('status', RegistrationStatus::Pending->value),
            ])
            ->orderBy('start_date')
            ->get();
    }

    /**
     * One line per kind of alert, with the total behind it, like the bell but grouped.
     *
     * @param  Collection<int, StaffAlert>  $alerts
     * @return list<array{kind: string, group: string, title: string, detail: ?string, unread: bool, items: Collection<int, StaffAlert>}>
     */
    private function summarize(Collection $alerts): array
    {
        $kinds = [
            'registrations.program' => fn (int $total, int $n) => [en_num($total).' '.$this->requests($total).' بانتظار القبول', $this->across($n, 'برنامج واحد', 'برنامجين', 'برامج')],
            'delivery.broadcast' => fn (int $total, int $n) => ['رسائل جماعية لم تصل إلى '.en_num($total).' '.($total === 1 ? 'شخص' : 'أشخاص'), $this->across($n, 'رسالة واحدة', 'رسالتين', 'رسائل')],
            'delivery.email' => fn (int $total, int $n) => [en_num($total).' '.($total === 1 ? 'رسالة بريد' : 'رسائل بريد').' إلى المسجلين لم تصل', $this->across($n, 'برنامج واحد', 'برنامجين', 'برامج')],
            'program.attendance' => fn (int $total, int $n) => ['التحضير مفتوح الآن', $this->across($n, 'يوم تحضير واحد', 'يوما تحضير', 'أيام تحضير', in: false)],
            'program.survey' => fn (int $total, int $n) => ['استبيانات تُغلق قريباً بنسبة تعبئة منخفضة', $this->across($n, 'استبيان واحد', 'استبيانان', 'استبيانات', in: false)],
            'program.certificates' => fn (int $total, int $n) => ['برامج انتهت ولم تُصدر شهاداتها', $this->across($n, 'برنامج واحد', 'برنامجان', 'برامج', in: false)],
        ];

        $out = [];
        foreach ($kinds as $kind => $describe) {
            $items = $alerts->where('kind', $kind)->values();
            if ($items->isEmpty()) {
                continue;
            }
            [$title, $detail] = $describe((int) $items->sum('count'), $items->count());
            $out[] = [
                'kind' => $kind,
                'group' => explode('.', $kind)[0],
                'title' => $title,
                'detail' => $detail,
                'unread' => $items->contains('unread', true),
                'items' => $items,
            ];
        }

        return $out;
    }

    private function requests(int $n, string $one = 'طلب', string $few = 'طلبات', string $many = 'طلباً'): string
    {
        return $n === 1 ? $one : ($n <= 10 ? $few : $many);
    }

    private function across(int $n, string $one, string $two, string $few, bool $in = true): string
    {
        $phrase = match (true) {
            $n === 1 => $one,
            $n === 2 => $two,
            default => en_num($n).' '.$few,
        };

        return ($in ? 'في ' : '').$phrase;
    }
}

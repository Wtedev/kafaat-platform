<?php

namespace App\Services\StaffAlerts;

use App\Enums\ProgramBroadcastStatus;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\TrainingProgramResource;
use App\Filament\Resources\VolunteerOpportunityResource;
use App\Models\EmailLog;
use App\Models\ProgramAttendanceLink;
use App\Models\ProgramBroadcast;
use App\Models\ProgramRegistration;
use App\Models\ProgramSurvey;
use App\Models\StaffAlert;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\Surveys\ProgramSurveyService;
use App\Support\StaffUi\StaffUiAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Staff alerts are things that need someone to act, not a feed of everything
 * that happened. Each alert is computed from the current state of the data:
 * it appears when there is something to do, its count follows the data, and
 * it goes away by itself once the work is done.
 *
 * Every active staff member sees the alerts their permissions cover.
 * Support tickets are not part of these alerts.
 * Experience houses are a separate system and are never part of these alerts.
 */
final class StaffAlerts
{
    public const GROUPS = [
        'registrations' => ['label' => 'طلبات التسجيل', 'icon' => 'user-plus'],
        'delivery' => ['label' => 'مشاكل الإرسال', 'icon' => 'mail-warning'],
        'program' => ['label' => 'تذكيرات البرامج', 'icon' => 'calendar-clock'],
    ];

    private const REFRESH_SECONDS = 60;

    /** Recompute alerts unless it was done in the last minute. */
    public function refreshIfStale(): void
    {
        if (Cache::add('staff-alerts:refreshed', true, self::REFRESH_SECONDS)) {
            try {
                $this->refresh();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function refresh(?Carbon $now = null): void
    {
        $now ??= now();
        $wanted = collect()
            ->merge($this->pendingProgramRegistrations())
            ->merge($this->pendingVolunteerRegistrations())
            ->merge($this->failedBroadcasts($now))
            ->merge($this->failedProgramEmails($now))
            ->merge($this->openAttendance($now))
            ->merge($this->surveysClosingLow($now))
            ->merge($this->endedWithoutCertificates($now))
            ->keyBy('key');

        DB::transaction(function () use ($wanted, $now): void {
            $existing = StaffAlert::query()->whereIn('key', $wanted->keys()->all())->get()->keyBy('key');

            foreach ($wanted as $key => $data) {
                /** @var StaffAlert|null $alert */
                $alert = $existing->get($key);
                if ($alert === null) {
                    StaffAlert::query()->create([...$data, 'changed_at' => $now]);

                    continue;
                }

                $grew = $data['count'] > $alert->count || $alert->resolved_at !== null;
                $alert->fill([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'count' => $data['count'],
                    'payload' => $data['payload'],
                    'resolved_at' => null,
                ]);
                if ($grew) {
                    $alert->changed_at = $now;
                }
                // A dismissed alert comes back only when there is more than when it was dismissed.
                if ($alert->dismissed_at !== null && $data['count'] > (int) $alert->dismissed_count) {
                    $alert->dismissed_at = null;
                    $alert->dismissed_by = null;
                    $alert->dismissed_count = null;
                    $alert->changed_at = $now;
                }
                $alert->save();
            }

            StaffAlert::query()
                ->whereNull('resolved_at')
                ->whereNotIn('key', $wanted->keys()->all() ?: ['-'])
                ->update(['resolved_at' => $now, 'dismissed_at' => null, 'dismissed_by' => null, 'dismissed_count' => null]);
        });
    }

    /**
     * Live alerts this person may see, newest change first.
     *
     * @return Collection<int, StaffAlert>
     */
    public function visibleFor(User $user): Collection
    {
        $groups = $this->groupsFor($user);
        if ($groups === []) {
            return collect();
        }

        $alerts = StaffAlert::query()->live()->orderByDesc('changed_at')->orderByDesc('id')->get()
            ->filter(fn (StaffAlert $alert): bool => in_array($alert->group(), $groups, true))
            ->values();

        $reads = DB::table('staff_alert_reads')
            ->where('user_id', $user->id)
            ->whereIn('staff_alert_id', $alerts->modelKeys())
            ->pluck('read_at', 'staff_alert_id');

        $order = array_flip(array_keys(self::GROUPS));

        // New first, then requests before problems before reminders, newest change first.
        return $alerts->each(function (StaffAlert $alert) use ($reads): void {
            $readAt = $reads->get($alert->id);
            $alert->setAttribute('unread', $readAt === null || Carbon::parse($readAt)->lt($alert->changed_at));
        })->sortBy([
            fn (StaffAlert $a, StaffAlert $b): int => (int) $b->unread <=> (int) $a->unread,
            fn (StaffAlert $a, StaffAlert $b): int => ($order[$a->group()] ?? 9) <=> ($order[$b->group()] ?? 9),
            fn (StaffAlert $a, StaffAlert $b): int => $b->changed_at <=> $a->changed_at,
        ])->values();
    }

    public function unreadCount(User $user): int
    {
        return $this->visibleFor($user)->where('unread', true)->count();
    }

    public function markRead(User $user, StaffAlert $alert): void
    {
        DB::table('staff_alert_reads')->upsert(
            [['staff_alert_id' => $alert->id, 'user_id' => $user->id, 'read_at' => now()]],
            ['staff_alert_id', 'user_id'],
            ['read_at'],
        );
    }

    public function markAllRead(User $user): void
    {
        $rows = $this->visibleFor($user)
            ->map(fn (StaffAlert $a): array => ['staff_alert_id' => $a->id, 'user_id' => $user->id, 'read_at' => now()])
            ->values()->all();
        if ($rows !== []) {
            DB::table('staff_alert_reads')->upsert($rows, ['staff_alert_id', 'user_id'], ['read_at']);
        }
    }

    /** Hide an alert for everyone until it grows again (only for things nobody can "finish"). */
    public function dismiss(User $user, StaffAlert $alert): void
    {
        $alert->update(['dismissed_at' => now(), 'dismissed_by' => $user->id, 'dismissed_count' => $alert->count]);
    }

    public function canSee(User $user, StaffAlert $alert): bool
    {
        return in_array($alert->group(), $this->groupsFor($user), true);
    }

    /** Where the alert takes this person: the new staff pages when they use them, else the admin panel. */
    public function linkFor(StaffAlert $alert, User $user): string
    {
        $p = (array) $alert->payload;
        $newUi = StaffUiAccess::seesNewUi($user);
        $program = isset($p['program']) ? (int) $p['program'] : 0;
        $programTab = fn (string $tab, array $query = []): string => $newUi
            ? route('staff-ui.programs.tab', [$program, $tab]).($query === [] ? '' : '?'.http_build_query($query))
            : TrainingProgramResource::getUrl('view', ['record' => $program]);

        return match ($alert->kind) {
            'registrations.program' => $programTab('registrations', ['status' => 'pending']),
            'registrations.volunteer' => VolunteerOpportunityResource::getUrl('view', ['record' => (int) ($p['opportunity'] ?? 0)]),
            'delivery.broadcast', 'delivery.email' => $programTab('broadcasts'),
            'program.attendance' => $programTab('attendance'),
            'program.survey' => $programTab('surveys'),
            'program.certificates' => $programTab('certificates'),
            default => url('/admin'),
        };
    }

    /** Problems and reminders can't always be "finished", so they can be dismissed; requests go away when handled. */
    public function isDismissible(StaffAlert $alert): bool
    {
        return in_array($alert->kind, ['delivery.email', 'delivery.broadcast', 'program.survey', 'program.certificates'], true);
    }

    /**
     * @return list<string>
     */
    private function groupsFor(User $user): array
    {
        if (! $user->is_active || ! $user->canAccessFilamentAdmin()) {
            return [];
        }

        $groups = [];
        if ($user->can('viewAny', TrainingProgram::class)) {
            array_push($groups, 'registrations', 'delivery', 'program');
        } elseif ($user->isAdmin() || $user->checkPermissionTo('volunteering.view')) {
            $groups[] = 'registrations';
        }

        return $groups;
    }

    /** @return list<array<string, mixed>> */
    private function pendingProgramRegistrations(): array
    {
        $counts = ProgramRegistration::query()
            ->where('status', RegistrationStatus::Pending->value)
            ->selectRaw('training_program_id, count(*) as c')
            ->groupBy('training_program_id')
            ->pluck('c', 'training_program_id');

        return TrainingProgram::query()
            ->whereIn('id', $counts->keys()->all())
            ->where('status', '!=', ProgramStatus::Archived->value)
            ->get(['id', 'title'])
            ->map(function (TrainingProgram $program) use ($counts): array {
                $c = (int) $counts[$program->id];

                return [
                    'key' => 'registrations.program:'.$program->id,
                    'kind' => 'registrations.program',
                    'title' => en_num($c).' '.($c === 1 ? 'طلب' : ($c <= 10 ? 'طلبات' : 'طلباً')).' بانتظار القبول',
                    'body' => $program->title,
                    'count' => $c,
                    'payload' => ['program' => $program->id],
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function pendingVolunteerRegistrations(): array
    {
        $counts = VolunteerRegistration::query()
            ->where('status', RegistrationStatus::Pending->value)
            ->selectRaw('opportunity_id, count(*) as c')
            ->groupBy('opportunity_id')
            ->pluck('c', 'opportunity_id');

        return VolunteerOpportunity::query()
            ->whereIn('id', $counts->keys()->all())
            ->get(['id', 'title'])
            ->map(function (VolunteerOpportunity $opportunity) use ($counts): array {
                $c = (int) $counts[$opportunity->id];

                return [
                    'key' => 'registrations.volunteer:'.$opportunity->id,
                    'kind' => 'registrations.volunteer',
                    'title' => en_num($c).' '.($c === 1 ? 'طلب تطوع' : ($c <= 10 ? 'طلبات تطوع' : 'طلب تطوع')).' بانتظار القبول',
                    'body' => $opportunity->title,
                    'count' => $c,
                    'payload' => ['opportunity' => $opportunity->id],
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function failedBroadcasts(Carbon $now): array
    {
        return ProgramBroadcast::query()
            ->where('failed_count', '>', 0)
            ->whereIn('status', [
                ProgramBroadcastStatus::Completed->value,
                ProgramBroadcastStatus::CompletedWithErrors->value,
                ProgramBroadcastStatus::Failed->value,
            ])
            ->where('updated_at', '>=', $now->copy()->subDays(14))
            ->whereHas('trainingProgram', fn ($q) => $q->withoutTrashed())
            ->with('trainingProgram:id,title')
            ->get()
            ->map(fn (ProgramBroadcast $b): array => [
                'key' => 'delivery.broadcast:'.$b->id,
                'kind' => 'delivery.broadcast',
                'title' => 'رسالة لم تصل إلى '.en_num((int) $b->failed_count).' '.((int) $b->failed_count === 1 ? 'شخص' : 'أشخاص'),
                'body' => '«'.$b->subject.'» — '.$b->trainingProgram?->title,
                'count' => (int) $b->failed_count,
                'payload' => ['program' => $b->training_program_id, 'broadcast' => $b->id],
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function failedProgramEmails(Carbon $now): array
    {
        $counts = EmailLog::query()
            ->where('status', 'failed')
            ->whereNotNull('training_program_id')
            ->where('sent_at', '>=', $now->copy()->subDays(7))
            ->selectRaw('training_program_id, count(*) as c')
            ->groupBy('training_program_id')
            ->pluck('c', 'training_program_id');

        return TrainingProgram::query()
            ->whereIn('id', $counts->keys()->all())
            ->get(['id', 'title'])
            ->map(function (TrainingProgram $program) use ($counts): array {
                $c = (int) $counts[$program->id];

                return [
                    'key' => 'delivery.email:'.$program->id,
                    'kind' => 'delivery.email',
                    'title' => en_num($c).' '.($c === 1 ? 'رسالة بريد إلى المسجلين لم تصل' : 'رسائل بريد إلى المسجلين لم تصل'),
                    'body' => $program->title,
                    'count' => $c,
                    'payload' => ['program' => $program->id],
                ];
            })->all();
    }

    /** @return list<array<string, mixed>> */
    private function openAttendance(Carbon $now): array
    {
        return ProgramAttendanceLink::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('opens_at')
            ->where('opens_at', '<=', $now)
            ->where('closes_at', '>', $now)
            ->whereHas('program', fn ($q) => $q->withoutTrashed())
            ->with('program:id,title')
            ->get()
            ->map(fn (ProgramAttendanceLink $link): array => [
                'key' => 'program.attendance:'.$link->id,
                'kind' => 'program.attendance',
                'title' => 'التحضير مفتوح الآن: '.$link->name,
                'body' => $link->program?->title.' — حتى '.$link->closes_at->copy()->timezone(config('app.timezone'))->format('H:i'),
                'count' => 1,
                'payload' => ['program' => $link->training_program_id, 'link' => $link->id],
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function surveysClosingLow(Carbon $now): array
    {
        $service = app(ProgramSurveyService::class);

        return ProgramSurvey::query()
            ->where('opens_at', '<=', $now)
            ->whereBetween('closes_at', [$now, $now->copy()->addDays(2)])
            ->whereHas('program', fn ($q) => $q->withoutTrashed())
            ->with('program:id,title')
            ->get()
            ->map(function (ProgramSurvey $survey) use ($service): ?array {
                $eligible = $service->eligibleCount($survey);
                $answered = $service->responseCount($survey);
                if ($eligible === 0 || $answered / $eligible >= 0.5) {
                    return null;
                }

                return [
                    'key' => 'program.survey:'.$survey->id,
                    'kind' => 'program.survey',
                    'title' => 'يُغلق الاستبيان ال'.$survey->type->label().' قريبًا ولم يُعبّئه سوى '.en_num($answered).' من '.en_num($eligible),
                    'body' => $survey->program?->title,
                    'count' => $eligible - $answered,
                    'payload' => ['program' => $survey->training_program_id, 'survey' => $survey->id],
                ];
            })->filter()->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function endedWithoutCertificates(Carbon $now): array
    {
        $morph = (new TrainingProgram)->getMorphClass();

        return TrainingProgram::query()
            ->where('status', ProgramStatus::Published->value)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $now->toDateString())
            ->whereDate('end_date', '>=', $now->copy()->subDays(60)->toDateString())
            ->whereHas('registrations', fn ($q) => $q->whereIn('status', [RegistrationStatus::Approved->value, RegistrationStatus::Completed->value]))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('certificates')
                ->whereColumn('certificates.certificateable_id', 'training_programs.id')
                ->where('certificates.certificateable_type', $morph))
            ->get(['id', 'title', 'end_date'])
            ->map(fn (TrainingProgram $program): array => [
                'key' => 'program.certificates:'.$program->id,
                'kind' => 'program.certificates',
                'title' => 'انتهى البرنامج ولم تُصدر شهاداته',
                'body' => $program->title,
                'count' => 1,
                'payload' => ['program' => $program->id],
            ])->all();
    }
}

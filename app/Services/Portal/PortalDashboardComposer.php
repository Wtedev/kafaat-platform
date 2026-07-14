<?php

namespace App\Services\Portal;

use App\Enums\RegistrationStatus;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TeamMember;
use App\Models\TeamNotification;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\ProgramAcceptanceConditionEvaluator;
use App\Services\ProgressService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PortalDashboardComposer
{
    /**
     * @return array{
     *     activities: Collection<int, array<string, mixed>>,
     *     volunteerRows: Collection<int, array<string, mixed>>,
     *     suggestedPrograms: Collection<int, array<string, mixed>>,
     *     suggestedOpportunities: Collection<int, array<string, mixed>>,
     *     showVolunteerTeamDashboard: bool,
     *     volunteerTeamMemberRows: Collection<int, array<string, mixed>>,
     *     volunteerTeamNotifications: Collection<int, array<string, mixed>>,
     * }
     */
    public static function compose(User $user): array
    {
        $activities = self::composeActivities($user);
        $suggestions = self::composeSuggestions($user);
        $teamDash = self::composeVolunteerTeamDashboard($user);

        return [
            'activities' => $activities,
            'volunteerRows' => $suggestions['registeredVolunteerRows'],
            'suggestedPrograms' => $suggestions['suggestedPrograms'],
            'suggestedOpportunities' => $suggestions['suggestedOpportunities'],
            'showVolunteerTeamDashboard' => $teamDash['show'],
            'volunteerTeamMemberRows' => $teamDash['memberRows'],
            'volunteerTeamNotifications' => $teamDash['notificationRows'],
        ];
    }

    /**
     * @return array{
     *     registeredVolunteerRows: Collection<int, array<string, mixed>>,
     *     suggestedPrograms: Collection<int, array<string, mixed>>,
     *     suggestedOpportunities: Collection<int, array<string, mixed>>,
     * }
     */
    private static function composeSuggestions(User $user): array
    {
        $activeStatuses = [
            RegistrationStatus::Pending->value,
            RegistrationStatus::Approved->value,
            RegistrationStatus::Completed->value,
        ];

        $claimedProgramIds = $user->programRegistrations()
            ->whereIn('status', $activeStatuses)
            ->pluck('training_program_id')
            ->unique()
            ->filter()
            ->values();

        $claimedPathIds = $user->learningPathRegistrations()
            ->whereIn('status', $activeStatuses)
            ->pluck('learning_path_id')
            ->unique()
            ->filter()
            ->values();

        $claimedVolunteerIds = $user->volunteerRegistrations()
            ->whereIn('status', $activeStatuses)
            ->pluck('opportunity_id')
            ->unique()
            ->filter()
            ->values();

        $today = Carbon::today();
        $evaluator = app(ProgramAcceptanceConditionEvaluator::class);

        $programCandidates = TrainingProgram::query()
            ->published()
            ->standaloneCatalog()
            ->when($claimedProgramIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $claimedProgramIds))
            ->where(function ($q) use ($today): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->orderByRaw(
                'CASE WHEN registration_end IS NULL OR registration_end >= ? THEN 0 ELSE 1 END',
                [$today->toDateString()]
            )
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date')
            ->latest('published_at')
            ->limit(16)
            ->get();

        $eligiblePrograms = $programCandidates
            ->filter(fn (TrainingProgram $program): bool => $evaluator->isEligible($program, $user))
            ->values();

        $suggestedProgramModels = $eligiblePrograms->take(4);
        if ($suggestedProgramModels->count() < 4) {
            $filler = $programCandidates
                ->reject(fn (TrainingProgram $p): bool => $suggestedProgramModels->contains('id', $p->id))
                ->take(4 - $suggestedProgramModels->count());
            $suggestedProgramModels = $suggestedProgramModels->concat($filler)->values();
        }

        $pathCandidates = LearningPath::query()
            ->published()
            ->when($claimedPathIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $claimedPathIds))
            ->latest('published_at')
            ->limit(4)
            ->get();

        $suggestedPrograms = collect();

        foreach ($pathCandidates->take(2) as $path) {
            $suggestedPrograms->push(self::pathDiscoverRow($path));
        }

        foreach ($suggestedProgramModels as $program) {
            if ($suggestedPrograms->count() >= 4) {
                break;
            }
            $suggestedPrograms->push(self::programDiscoverRow(
                $program,
                eligible: $evaluator->isEligible($program, $user),
            ));
        }

        $registeredVolunteerRows = VolunteerOpportunity::query()
            ->whereIn('id', $claimedVolunteerIds)
            ->with([
                'registrations' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->latest('published_at')
            ->limit(8)
            ->get()
            ->map(fn (VolunteerOpportunity $opp): array => self::volunteerRow($opp));

        $suggestedOpportunities = VolunteerOpportunity::query()
            ->published()
            ->when($claimedVolunteerIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $claimedVolunteerIds))
            ->where(function ($q) use ($today): void {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->latest('published_at')
            ->limit(4)
            ->get()
            ->map(fn (VolunteerOpportunity $opp): array => self::volunteerDiscoverRow($opp));

        return [
            'registeredVolunteerRows' => $registeredVolunteerRows,
            'suggestedPrograms' => $suggestedPrograms->values(),
            'suggestedOpportunities' => $suggestedOpportunities,
        ];
    }

    /**
     * @return array{show: bool, memberRows: Collection<int, array<string, mixed>>, notificationRows: Collection<int, array<string, mixed>>}
     */
    private static function composeVolunteerTeamDashboard(User $user): array
    {
        if (! self::shouldShowVolunteerTeamDashboard($user)) {
            return [
                'show' => false,
                'memberRows' => collect(),
                'notificationRows' => collect(),
            ];
        }

        $teamIds = $user->volunteerTeams()
            ->where('volunteer_teams.is_active', true)
            ->pluck('volunteer_teams.id');

        if ($teamIds->isEmpty()) {
            return [
                'show' => true,
                'memberRows' => collect(),
                'notificationRows' => collect(),
            ];
        }

        $memberRows = TeamMember::query()
            ->whereIn('volunteer_team_id', $teamIds)
            ->with(['user', 'volunteerTeam'])
            ->get()
            ->unique('user_id')
            ->values()
            ->map(fn (TeamMember $m): array => [
                'name' => $m->user?->name ?? '—',
                'email' => $m->user?->email,
                'team_name' => $m->volunteerTeam?->name ?? '—',
            ]);

        $notificationRows = TeamNotification::query()
            ->whereIn('volunteer_team_id', $teamIds)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('volunteerTeam')
            ->latest('published_at')
            ->limit(15)
            ->get()
            ->map(fn (TeamNotification $n): array => [
                'title' => $n->title,
                'body' => $n->body,
                'team_name' => $n->volunteerTeam?->name ?? '—',
                'published_at' => $n->published_at,
            ]);

        return [
            'show' => true,
            'memberRows' => $memberRows,
            'notificationRows' => $notificationRows,
        ];
    }

    private static function shouldShowVolunteerTeamDashboard(User $user): bool
    {
        if ($user->volunteerTeams()->exists()) {
            return true;
        }

        return $user->hasRole('volunteer') || $user->role_type === 'volunteer';
    }

    private static function composeActivities(User $user): Collection
    {
        $user->loadMissing([
            'learningPathRegistrations.learningPath.programs',
            'programRegistrations.trainingProgram',
        ]);

        $pathRegs = $user->learningPathRegistrations;
        $progRegs = $user->programRegistrations;

        $rows = collect();

        foreach ($pathRegs->sortByDesc('updated_at') as $reg) {
            $rows->push(self::pathActivity($user, $reg));
        }

        foreach ($progRegs->sortByDesc('updated_at') as $reg) {
            $program = $reg->trainingProgram;
            if ($program !== null && $program->learning_path_id !== null) {
                continue;
            }

            $rows->push(self::programActivity($reg));
        }

        return $rows->values();
    }

    private static function pathActivity(User $user, PathRegistration $reg): array
    {
        $path = $reg->learningPath;
        $title = $path?->title ?? 'مسار تعليمي';
        $progress = null;

        if ($path && $reg->canAccessPathPrograms()) {
            $progress = app(ProgressService::class)->calculatePathProgress($user, $path);
        }

        [$statusLabel, $statusTone] = self::registrationUxMeta($reg->status);

        return [
            'kind' => 'path',
            'discover' => false,
            'sort_at' => $reg->updated_at,
            'title' => $title,
            'image_url' => $path?->imagePublicUrl(),
            'meta' => null,
            'type_label' => 'مسار',
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'progress' => $progress !== null ? min(100, max(0, $progress)) : null,
            'cta_label' => self::pathCtaLabel($reg),
            'cta_url' => self::pathCtaUrl($reg),
        ];
    }

    private static function programActivity(ProgramRegistration $reg): array
    {
        $program = $reg->trainingProgram;
        $title = $program?->title ?? 'برنامج تدريبي';
        $progress = $reg->attendance_percentage !== null
            ? (float) $reg->attendance_percentage
            : null;

        [$statusLabel, $statusTone] = self::registrationUxMeta($reg->status);

        return [
            'kind' => 'program',
            'discover' => false,
            'sort_at' => $reg->updated_at,
            'title' => $title,
            'image_url' => $program?->imagePublicUrl(),
            'meta' => self::programDateMeta($program),
            'type_label' => 'برنامج',
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'progress' => $progress !== null ? min(100, max(0, $progress)) : null,
            'cta_label' => self::programCtaLabel($reg),
            'cta_url' => self::programCtaUrl($reg),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function programDiscoverRow(TrainingProgram $program, bool $eligible = true): array
    {
        return [
            'kind' => 'program',
            'discover' => true,
            'eligible' => $eligible,
            'sort_at' => $program->published_at ?? $program->updated_at,
            'title' => $program->title,
            'image_url' => $program->imagePublicUrl(),
            'meta' => self::programDateMeta($program),
            'type_label' => 'برنامج',
            'status_label' => $eligible ? 'مقترح لك' : 'متاح',
            'status_tone' => $eligible ? 'secondary' : 'slate',
            'progress' => null,
            'cta_label' => 'اطّلع وسجّل',
            'cta_url' => route('public.programs.show', $program),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pathDiscoverRow(LearningPath $path): array
    {
        return [
            'kind' => 'path',
            'discover' => true,
            'eligible' => true,
            'sort_at' => $path->published_at ?? $path->updated_at,
            'title' => $path->title,
            'image_url' => $path->imagePublicUrl(),
            'meta' => null,
            'type_label' => 'مسار',
            'status_label' => 'مقترح لك',
            'status_tone' => 'secondary',
            'progress' => null,
            'cta_label' => 'اطّلع وسجّل',
            'cta_url' => route('public.paths.show', $path),
        ];
    }

    private static function programDateMeta(?TrainingProgram $program): ?string
    {
        if ($program === null || $program->start_date === null) {
            return null;
        }

        $start = ar_date($program->start_date);
        if ($program->end_date !== null) {
            return $start.' — '.ar_date($program->end_date);
        }

        return 'يبدأ '.$start;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function registrationUxMeta(RegistrationStatus $status): array
    {
        return match ($status) {
            RegistrationStatus::Pending => ['مسجل', 'accent'],
            RegistrationStatus::Approved => ['قيد التقدم', 'primary'],
            RegistrationStatus::Completed => ['مكتمل', 'secondary'],
            RegistrationStatus::Rejected, RegistrationStatus::Cancelled => ['غير مسجل', 'muted'],
        };
    }

    private static function pathCtaLabel(PathRegistration $reg): string
    {
        return match ($reg->status) {
            RegistrationStatus::Pending => 'عرض',
            RegistrationStatus::Approved, RegistrationStatus::Completed => 'متابعة',
            default => 'عرض',
        };
    }

    private static function pathCtaUrl(PathRegistration $reg): string
    {
        $path = $reg->learningPath;
        if (! $path) {
            return route('portal.paths');
        }

        if ($reg->canAccessPathPrograms()) {
            return route('portal.paths.show', $path);
        }

        return route('portal.paths');
    }

    private static function programCtaLabel(ProgramRegistration $reg): string
    {
        return match ($reg->status) {
            RegistrationStatus::Pending => 'عرض',
            RegistrationStatus::Approved => 'متابعة',
            RegistrationStatus::Completed => 'عرض',
            default => 'عرض',
        };
    }

    private static function programCtaUrl(ProgramRegistration $reg): string
    {
        $program = $reg->trainingProgram;
        if ($program !== null) {
            return route('portal.programs.show', $program);
        }

        return route('portal.programs');
    }

    /**
     * @return array<string, mixed>
     */
    private static function volunteerRow(VolunteerOpportunity $opp): array
    {
        $reg = $opp->registrations->first();

        if (! $reg) {
            $stateLabel = 'غير مسجل';
            $stateTone = 'slate';
            $ctaLabel = 'تسجيل';
            $ctaUrl = route('public.volunteering.show', $opp->slug);
        } else {
            [$stateLabel, $stateTone] = match ($reg->status) {
                RegistrationStatus::Completed => ['مكتمل', 'secondary'],
                RegistrationStatus::Pending, RegistrationStatus::Approved => ['مسجل', 'primary'],
                default => ['غير مسجل', 'muted'],
            };
            $ctaLabel = $reg->status === RegistrationStatus::Completed ? 'عرض' : 'متابعة';
            $ctaUrl = route('public.volunteering.show', $opp->slug);
        }

        return [
            'title' => $opp->title,
            'image_url' => $opp->imagePublicUrl(),
            'hours' => $opp->hours_expected,
            'meta' => self::volunteerDateMeta($opp),
            'state_label' => $stateLabel,
            'state_tone' => $stateTone,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function volunteerDiscoverRow(VolunteerOpportunity $opp): array
    {
        return [
            'title' => $opp->title,
            'image_url' => $opp->imagePublicUrl(),
            'hours' => $opp->hours_expected,
            'meta' => self::volunteerDateMeta($opp),
            'state_label' => 'مفتوحة',
            'state_tone' => 'secondary',
            'cta_label' => 'اطّلع وسجّل',
            'cta_url' => route('public.volunteering.show', $opp->slug),
        ];
    }

    private static function volunteerDateMeta(VolunteerOpportunity $opp): ?string
    {
        if ($opp->start_date === null) {
            return null;
        }

        $start = ar_date($opp->start_date);
        if ($opp->end_date !== null) {
            return $start.' — '.ar_date($opp->end_date);
        }

        return 'تبدأ '.$start;
    }
}

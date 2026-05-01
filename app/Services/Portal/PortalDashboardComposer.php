<?php

namespace App\Services\Portal;

use App\Enums\RegistrationStatus;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Support\Collection;

final class PortalDashboardComposer
{
    /**
     * @return array{activities: Collection<int, array<string, mixed>>, volunteerRows: Collection<int, array<string, mixed>>}
     */
    public static function compose(User $user): array
    {
        $activities = self::composeActivities($user);

        $volunteerRows = VolunteerOpportunity::query()
            ->published()
            ->with([
                'registrations' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->latest('published_at')
            ->limit(10)
            ->get()
            ->map(fn (VolunteerOpportunity $opp): array => self::volunteerRow($opp));

        return [
            'activities' => $activities,
            'volunteerRows' => $volunteerRows,
        ];
    }

    private static function composeActivities(User $user): Collection
    {
        $user->loadMissing([
            'learningPathRegistrations.learningPath.courses',
            'programRegistrations.trainingProgram',
        ]);

        $pathRegs = $user->learningPathRegistrations;
        $progRegs = $user->programRegistrations;

        $claimedPathIds = $pathRegs->pluck('learning_path_id')->unique()->filter()->values();
        $claimedProgIds = $progRegs->pluck('training_program_id')->unique()->filter()->values();

        $rows = collect();

        foreach ($pathRegs->sortByDesc('updated_at') as $reg) {
            $rows->push(self::pathActivity($user, $reg));
        }

        foreach ($progRegs->sortByDesc('updated_at') as $reg) {
            $rows->push(self::programActivity($reg));
        }

        $discover = collect();

        $extraPaths = LearningPath::query()
            ->published()
            ->when($claimedPathIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $claimedPathIds))
            ->latest('published_at')
            ->limit(3)
            ->get();

        foreach ($extraPaths as $path) {
            $discover->push(self::pathDiscoverRow($path));
        }

        $extraProgs = TrainingProgram::query()
            ->published()
            ->when($claimedProgIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $claimedProgIds))
            ->latest('published_at')
            ->limit(3)
            ->get();

        foreach ($extraProgs as $program) {
            $discover->push(self::programDiscoverRow($program));
        }

        $discover = $discover->sortByDesc(fn (array $r): int => ($r['sort_at'] instanceof \DateTimeInterface) ? $r['sort_at']->getTimestamp() : 0)->values();

        return $rows->concat($discover)->values();
    }

    private static function pathActivity(User $user, PathRegistration $reg): array
    {
        $path = $reg->learningPath;
        $title = $path?->title ?? 'مسار تعليمي';
        $progress = null;

        if ($path && $reg->canAccessCourses()) {
            $courseIds = $path->courses()->pluck('id');
            if ($courseIds->isNotEmpty()) {
                $avg = $user->courseProgress()
                    ->whereIn('path_course_id', $courseIds)
                    ->avg('progress_percentage');
                $progress = $avg !== null ? (float) $avg : null;
            }
        }

        [$statusLabel, $statusTone] = self::registrationUxMeta($reg->status);

        return [
            'kind' => 'path',
            'discover' => false,
            'sort_at' => $reg->updated_at,
            'title' => $title,
            'type_label' => 'لقاء',
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
            'type_label' => 'برنامج',
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'progress' => $progress !== null ? min(100, max(0, $progress)) : null,
            'cta_label' => self::programCtaLabel($reg),
            'cta_url' => self::programCtaUrl($reg),
        ];
    }

    private static function pathDiscoverRow(LearningPath $path): array
    {
        return [
            'kind' => 'path',
            'discover' => true,
            'sort_at' => $path->published_at ?? $path->updated_at,
            'title' => $path->title,
            'type_label' => 'لقاء',
            'status_label' => 'غير مسجل',
            'status_tone' => 'slate',
            'progress' => null,
            'cta_label' => 'تسجيل',
            'cta_url' => route('public.paths.show', $path),
        ];
    }

    private static function programDiscoverRow(TrainingProgram $program): array
    {
        return [
            'kind' => 'program',
            'discover' => true,
            'sort_at' => $program->published_at ?? $program->updated_at,
            'title' => $program->title,
            'type_label' => 'برنامج',
            'status_label' => 'غير مسجل',
            'status_tone' => 'slate',
            'progress' => null,
            'cta_label' => 'تسجيل',
            'cta_url' => route('public.programs.show', $program),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function registrationUxMeta(RegistrationStatus $status): array
    {
        return match ($status) {
            RegistrationStatus::Pending => ['مسجل', 'indigo'],
            RegistrationStatus::Approved => ['قيد التقدم', 'blue'],
            RegistrationStatus::Completed => ['مكتمل', 'emerald'],
            RegistrationStatus::Rejected, RegistrationStatus::Cancelled => ['غير مسجل', 'slate'],
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

        if ($reg->canAccessCourses()) {
            return route('portal.paths.courses', $path);
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
        if ($program) {
            return route('public.programs.show', $program);
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
                RegistrationStatus::Completed => ['مكتمل', 'emerald'],
                RegistrationStatus::Pending, RegistrationStatus::Approved => ['مسجل', 'indigo'],
                default => ['غير مسجل', 'slate'],
            };
            $ctaLabel = $reg->status === RegistrationStatus::Completed ? 'عرض' : 'متابعة';
            $ctaUrl = route('public.volunteering.show', $opp->slug);
        }

        return [
            'title' => $opp->title,
            'hours' => $opp->hours_expected,
            'state_label' => $stateLabel,
            'state_tone' => $stateTone,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
        ];
    }
}

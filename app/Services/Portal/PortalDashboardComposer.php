<?php

namespace App\Services\Portal;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Collection;

final class PortalDashboardComposer
{
    /**
     * @return array{
     *     activities: Collection<int, array<string, mixed>>,
     * }
     */
    public static function compose(User $user): array
    {
        return [
            'activities' => self::composeActivities($user),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function composeActivities(User $user): Collection
    {
        $user->loadMissing([
            'programRegistrations.trainingProgram',
        ]);

        $rows = collect();

        foreach ($user->programRegistrations->sortByDesc('updated_at') as $reg) {
            $program = $reg->trainingProgram;
            if ($program === null
                || $program->status !== ProgramStatus::Published) {
                continue;
            }

            $rows->push(self::programActivity($reg));
        }

        return $rows->values();
    }

    private static function programActivity(ProgramRegistration $reg): array
    {
        $program = $reg->trainingProgram;
        $title = $program?->title ?? 'برنامج تدريبي';
        $progress = ($pct = $reg->effectiveAttendancePercentage()) !== null
            ? (float) $pct
            : null;

        [$statusLabel, $statusTone] = self::registrationUxMeta($reg->status);

        return [
            'kind' => 'program',
            'discover' => false,
            'sort_at' => $reg->updated_at,
            'title' => $title,
            'image_url' => $program?->imagePublicUrl(),
            'type_label' => 'برنامج',
            'status_label' => $statusLabel,
            'status_tone' => $statusTone,
            'progress' => $progress !== null ? min(100, max(0, $progress)) : null,
            'cta_label' => self::programCtaLabel($reg),
            'cta_url' => self::programCtaUrl($reg),
        ];
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
        if ($program !== null && filled($program->slug)) {
            return route('public.programs.show', $program->slug);
        }

        return route('portal.programs');
    }
}

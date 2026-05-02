<?php

namespace App\Support;

use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Filament-facing labels for training entity access (non-authoritative; policies remain source of truth).
 */
final class FilamentTrainingAccess
{
    public static function programAccessLabel(?User $user, TrainingProgram $program): string
    {
        if ($user === null) {
            return '—';
        }

        if (TrainingEntityAuthorization::adminBypass($user)) {
            return 'مسؤول النظام';
        }

        if (! TrainingEntityAuthorization::isActive($user)) {
            return 'غير نشط';
        }

        if ($user->can('update', $program)) {
            if (TrainingEntityAuthorization::isOwnerOfProgram($user, $program)) {
                return 'المالك';
            }
            if (TrainingEntityAuthorization::isCreatorOfProgram($user, $program)) {
                return 'المنشئ';
            }
            if (TrainingEntityAuthorization::isEditorOfProgram($user, $program)) {
                return 'محرر';
            }

            return 'محرر';
        }

        if ($user->can('view', $program)) {
            return 'عارض';
        }

        return '—';
    }

    public static function pathAccessLabel(?User $user, LearningPath $path): string
    {
        if ($user === null) {
            return '—';
        }

        if (TrainingEntityAuthorization::adminBypass($user)) {
            return 'مسؤول النظام';
        }

        if (! TrainingEntityAuthorization::isActive($user)) {
            return 'غير نشط';
        }

        if ($user->can('update', $path)) {
            if (TrainingEntityAuthorization::isOwnerOfPath($user, $path)) {
                return 'المالك';
            }
            if (TrainingEntityAuthorization::isCreatorOfPath($user, $path)) {
                return 'المنشئ';
            }
            if (TrainingEntityAuthorization::isEditorOfPath($user, $path)) {
                return 'محرر';
            }

            return 'محرر';
        }

        if ($user->can('view', $path)) {
            return 'عارض';
        }

        return '—';
    }

    public static function viewOnlyNotice(): string
    {
        return 'يمكنك عرض هذا السجل؛ التعديل مقتصر على الفريق المسؤول (المالك والمحرّرون المعتمدون).';
    }

    /**
     * Effective permission for a team member on a training program (policy-based).
     */
    public static function teamMemberPermissionLabel(User $subject, TrainingProgram $program): string
    {
        if (Gate::forUser($subject)->allows('manageEditors', $program)) {
            return 'إدارة الصلاحيات';
        }

        if (Gate::forUser($subject)->allows('update', $program)) {
            return 'تعديل';
        }

        if (Gate::forUser($subject)->allows('view', $program)) {
            return 'عرض فقط';
        }

        return '—';
    }

    /**
     * Combined role labels (منشئ / مسؤول / محرر) for a single row.
     */
    public static function teamMemberRoleLabels(User $subject, TrainingProgram $program): string
    {
        $program->loadMissing('editors');

        $bits = [];
        if ($program->created_by !== null && (int) $subject->id === (int) $program->created_by) {
            $bits[] = 'منشئ';
        }
        if ($program->owner_id !== null && (int) $subject->id === (int) $program->owner_id) {
            $bits[] = 'مسؤول';
        }
        if ($program->editors->contains(fn (User $u): bool => (int) $u->id === (int) $subject->id)) {
            $bits[] = 'محرر';
        }

        return $bits === [] ? '—' : implode('، ', array_unique($bits));
    }

    /**
     * Pivot attach date for editors; null when the user is only creator/owner without pivot row.
     */
    public static function teamMemberAttachedAtLabel(User $subject, TrainingProgram $program): string
    {
        $program->loadMissing('editors');
        $match = $program->editors->firstWhere('id', $subject->id);
        if ($match?->pivot?->created_at !== null) {
            return $match->pivot->created_at->translatedFormat('j F Y');
        }

        return '—';
    }

    public static function pathTeamMemberPermissionLabel(User $subject, LearningPath $path): string
    {
        if (Gate::forUser($subject)->allows('manageEditors', $path)) {
            return 'إدارة الصلاحيات';
        }

        if (Gate::forUser($subject)->allows('update', $path)) {
            return 'تعديل';
        }

        if (Gate::forUser($subject)->allows('view', $path)) {
            return 'عرض فقط';
        }

        return '—';
    }

    public static function pathTeamMemberRoleLabels(User $subject, LearningPath $path): string
    {
        $path->loadMissing('editors');

        $bits = [];
        if ($path->created_by !== null && (int) $subject->id === (int) $path->created_by) {
            $bits[] = 'منشئ';
        }
        if ($path->owner_id !== null && (int) $subject->id === (int) $path->owner_id) {
            $bits[] = 'مسؤول';
        }
        if ($path->editors->contains(fn (User $u): bool => (int) $u->id === (int) $subject->id)) {
            $bits[] = 'محرر';
        }

        return $bits === [] ? '—' : implode('، ', array_unique($bits));
    }

    public static function pathTeamMemberAttachedAtLabel(User $subject, LearningPath $path): string
    {
        $path->loadMissing('editors');
        $match = $path->editors->firstWhere('id', $subject->id);
        if ($match?->pivot?->created_at !== null) {
            return $match->pivot->created_at->translatedFormat('j F Y');
        }

        return '—';
    }
}

<?php

namespace App\Policies;

use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Support\FilamentAssignmentVisibility;
use App\Support\StaffFilamentRoles;
use Illuminate\Database\Eloquent\Builder;

class SendInAppNotificationPolicy
{
    /**
     * صفحة «إرسال تنبيه»: لمن يملك صلاحية الإرسال.
     */
    public function accessPage(User $user): bool
    {
        return $user->is_active && $user->can('send_notifications');
    }

    public function isAdmin(User $user): bool
    {
        return FilamentAssignmentVisibility::bypasses($user);
    }

    public function isTrainingManager(User $user): bool
    {
        return StaffFilamentRoles::isProgramsActivitiesManager($user)
            || $user->hasAnyRole(StaffFilamentRoles::TRAINING_COORDINATOR);
    }

    public function isVolunteeringManager(User $user): bool
    {
        return StaffFilamentRoles::isProgramsActivitiesManager($user)
            || $user->hasAnyRole(StaffFilamentRoles::VOLUNTEERING_COORDINATOR);
    }

    /**
     * @return array<string, string>
     */
    public function availableTargetKinds(User $sender): array
    {
        if (! $sender->is_active) {
            return [];
        }

        if (! $sender->can('send_notifications')) {
            return [];
        }

        $kinds = [
            'user' => 'مستخدم محدد',
        ];

        if ($this->isAdmin($sender) || $this->isTrainingManager($sender) || $this->isVolunteeringManager($sender)) {
            $kinds['role'] = 'حسب الدور';
        }

        if ($this->isAdmin($sender) || $this->isTrainingManager($sender)) {
            $kinds['program'] = 'مستفيدو برنامج تدريبي';
        }

        return $kinds;
    }

    public function canUseTargetKind(User $sender, string $kind): bool
    {
        return array_key_exists($kind, $this->availableTargetKinds($sender));
    }

    /**
     * @return array<string, string>
     */
    public function availableRoleOptions(User $sender): array
    {
        if ($this->isAdmin($sender)) {
            return [
                'staff' => 'جميع الموظفين',
                'all_beneficiaries' => 'جميع المستفيدين',
            ];
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($sender)) {
            return [
                'trainees' => 'المتدربون والمستفيدون',
                'volunteers' => 'المتطوعون',
            ];
        }

        if ($this->isTrainingManager($sender) && ! $this->isAdmin($sender)) {
            return ['trainees' => 'المتدربون والمستفيدون'];
        }

        if ($this->isVolunteeringManager($sender)) {
            return ['volunteers' => 'المتطوعون'];
        }

        return [];
    }

    public function eligibleRecipientUsersQuery(User $sender): Builder
    {
        $q = User::query()->where('is_active', true);

        if (! $this->isAdmin($sender)) {
            $q->where('role_type', '!=', 'admin')
                ->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', 'admin'));
        }

        if ($this->isAdmin($sender)) {
            return $q->where(function (Builder $sub): void {
                $sub->whereIn('role_type', ['staff', 'admin', 'trainee', 'beneficiary', 'volunteer'])
                    ->orWhereHas('roles', fn (Builder $r) => $r->whereIn('name', [
                        'admin',
                        ...RbacCatalog::staffRoleNames(),
                        'trainee', 'volunteer',
                    ]));
            });
        }

        if (StaffFilamentRoles::isProgramsActivitiesManager($sender) && ! $this->isAdmin($sender)) {
            return $q->where(function (Builder $sub): void {
                $sub->whereIn('role_type', ['trainee', 'beneficiary', 'volunteer'])
                    ->orWhereHas('roles', fn (Builder $r) => $r->whereIn('name', ['trainee', 'volunteer']));
            });
        }

        if ($this->isTrainingManager($sender) && ! $this->isAdmin($sender)) {
            return $q->where(function (Builder $sub): void {
                $sub->whereIn('role_type', ['trainee', 'beneficiary'])
                    ->orWhereHas('roles', fn (Builder $r) => $r->where('name', 'trainee'));
            });
        }

        if ($this->isVolunteeringManager($sender)) {
            return $q->where(function (Builder $sub): void {
                $sub->where('role_type', 'volunteer')
                    ->orWhereHas('roles', fn (Builder $r) => $r->where('name', 'volunteer'));
            });
        }

        return $q->whereRaw('1 = 0');
    }

    public function canTargetUser(User $sender, User $target): bool
    {
        if (! $target->is_active) {
            return false;
        }

        return $this->eligibleRecipientUsersQuery($sender)->whereKey($target->id)->exists();
    }

    public function canTargetRole(User $sender, string $roleKey): bool
    {
        return array_key_exists($roleKey, $this->availableRoleOptions($sender));
    }

    public function canTargetProgram(User $sender, TrainingProgram $program): bool
    {
        return FilamentAssignmentVisibility::userManagesTrainingProgram($sender, $program);
    }

    /**
     * @return Builder<TrainingProgram>
     */
    public function eligibleTrainingProgramsQuery(User $sender): Builder
    {
        $q = TrainingProgram::query()->orderBy('title');

        if (FilamentAssignmentVisibility::bypasses($sender)) {
            return $q;
        }

        if ($sender->hasAnyRole(StaffFilamentRoles::TRAINING_COORDINATOR) || StaffFilamentRoles::isProgramsActivitiesManager($sender)) {
            return $q->forFilamentAssignmentAccess($sender);
        }

        return $q->whereRaw('1 = 0');
    }
}

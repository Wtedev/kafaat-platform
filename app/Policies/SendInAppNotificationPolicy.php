<?php

namespace App\Policies;

use App\Models\TeamMember;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerTeam;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Database\Eloquent\Builder;

class SendInAppNotificationPolicy
{
    /**
     * صفحة «إرسال تنبيه»: صلاحية إرسال عامة، أو قائد فريق تطوعي (معيّن على فريق).
     */
    public function accessPage(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->can('send_notifications') || $this->isVolunteerTeamLeader($user);
    }

    public function isVolunteerTeamLeader(User $user): bool
    {
        return VolunteerTeam::query()->where('assigned_to', $user->id)->exists();
    }

    public function isAdmin(User $user): bool
    {
        return FilamentAssignmentVisibility::bypasses($user);
    }

    public function isTrainingManager(User $user): bool
    {
        return $user->hasRole('training_manager');
    }

    public function isVolunteeringManager(User $user): bool
    {
        return $user->hasRole('volunteering_manager');
    }

    /**
     * @return array<string, string>
     */
    public function availableTargetKinds(User $sender): array
    {
        if (! $sender->is_active) {
            return [];
        }

        if (! $sender->can('send_notifications') && $this->isVolunteerTeamLeader($sender)) {
            return [
                'user' => 'مستخدم محدد',
                'team' => 'فريق تطوعي',
            ];
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

        $teamForManagers = ($this->isAdmin($sender) || $this->isVolunteeringManager($sender))
            && ! ($this->isTrainingManager($sender) && ! $this->isAdmin($sender));

        $teamForLeaderOnly = $this->isVolunteerTeamLeader($sender)
            && ! $this->isAdmin($sender)
            && ! $sender->hasRole('volunteering_manager');

        if ($teamForManagers || $teamForLeaderOnly) {
            $kinds['team'] = 'فريق تطوعي';
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
                        'admin', 'media_pr', 'media_employee', 'pr_employee', 'training_manager', 'volunteering_manager', 'staff',
                        'trainee', 'volunteer',
                    ]));
            });
        }

        if ($this->isVolunteerTeamLeader($sender) && ! $sender->can('send_notifications')) {
            $teamIds = VolunteerTeam::query()->where('assigned_to', $sender->id)->pluck('id');
            $memberIds = TeamMember::query()->whereIn('volunteer_team_id', $teamIds)->pluck('user_id')->unique();

            return $q->whereIn('id', $memberIds);
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

    public function canTargetTeam(User $sender, VolunteerTeam $team): bool
    {
        if (FilamentAssignmentVisibility::bypasses($sender)) {
            return true;
        }

        if ((int) $team->assigned_to === (int) $sender->id) {
            return true;
        }

        if ($sender->hasRole('volunteering_manager')) {
            return FilamentAssignmentVisibility::userManagesVolunteerTeam($sender, $team);
        }

        return false;
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

        if ($sender->hasRole('training_manager')) {
            return $q->forFilamentAssignmentAccess($sender);
        }

        return $q->whereRaw('1 = 0');
    }

    /**
     * @return Builder<VolunteerTeam>
     */
    public function eligibleTeamsQuery(User $sender): Builder
    {
        $q = VolunteerTeam::query()->orderBy('name');

        if (FilamentAssignmentVisibility::bypasses($sender)) {
            return $q;
        }

        if ($sender->hasRole('volunteering_manager')) {
            return $q->forFilamentAssignmentAccess($sender);
        }

        if ($this->isVolunteerTeamLeader($sender)) {
            return $q->where('assigned_to', $sender->id);
        }

        return $q->whereRaw('1 = 0');
    }
}

<?php

namespace App\Policies;

use App\Models\ProgramBroadcast;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\TrainingEntityAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProgramBroadcastPolicy
{
    use HandlesAuthorization;

    /**
     * Viewing broadcast history for a program — operational program access.
     */
    public function viewAny(User $user, ?TrainingProgram $program = null): bool
    {
        if ($program === null) {
            return false;
        }

        return TrainingEntityAuthorization::canViewOperationalProgram($user, $program);
    }

    public function view(User $user, ProgramBroadcast $broadcast): bool
    {
        $program = $broadcast->trainingProgram;

        if ($program === null) {
            return false;
        }

        return TrainingEntityAuthorization::canViewOperationalProgram($user, $program);
    }

    /**
     * Create draft / update draft / send / retry — emails.send + program manage scope.
     */
    public function create(User $user, TrainingProgram $program): bool
    {
        return $this->canManageBroadcasts($user, $program);
    }

    public function update(User $user, ProgramBroadcast $broadcast): bool
    {
        $program = $broadcast->trainingProgram;

        if ($program === null || ! $broadcast->contentIsMutable()) {
            return false;
        }

        return $this->canManageBroadcasts($user, $program);
    }

    public function send(User $user, ProgramBroadcast $broadcast): bool
    {
        $program = $broadcast->trainingProgram;

        if ($program === null) {
            return false;
        }

        return $this->canManageBroadcasts($user, $program);
    }

    public function retryFailed(User $user, ProgramBroadcast $broadcast): bool
    {
        $program = $broadcast->trainingProgram;

        if ($program === null) {
            return false;
        }

        return $this->canManageBroadcasts($user, $program);
    }

    public function delete(User $user, ProgramBroadcast $broadcast): bool
    {
        $program = $broadcast->trainingProgram;

        if ($program === null || ! $broadcast->canBeDeleted()) {
            return false;
        }

        return $this->canManageBroadcasts($user, $program);
    }

    private function canManageBroadcasts(User $user, TrainingProgram $program): bool
    {
        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        if (! TrainingEntityAuthorization::isActive($user)) {
            return false;
        }

        if (! $user->checkPermissionTo('emails.send')) {
            return false;
        }

        return TrainingEntityAuthorization::hasActiveProgramStakeholderRole($user, $program);
    }
}

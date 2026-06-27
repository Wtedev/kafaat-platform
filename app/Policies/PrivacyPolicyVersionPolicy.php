<?php

namespace App\Policies;

use App\Models\PrivacyPolicyVersion;
use App\Models\User;

class PrivacyPolicyVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('privacy_policy.view');
    }

    public function view(User $user, PrivacyPolicyVersion $version): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('privacy_policy.create');
    }

    public function update(User $user, PrivacyPolicyVersion $version): bool
    {
        return $user->can('privacy_policy.update_draft') && $version->isDraft();
    }

    public function delete(User $user, PrivacyPolicyVersion $version): bool
    {
        return $user->can('privacy_policy.update_draft') && $version->isDeletable();
    }

    public function publish(User $user, PrivacyPolicyVersion $version): bool
    {
        return $user->can('privacy_policy.publish') && $version->isDraft();
    }

    public function archive(User $user, PrivacyPolicyVersion $version): bool
    {
        return $user->can('privacy_policy.archive') && $version->isActive();
    }
}

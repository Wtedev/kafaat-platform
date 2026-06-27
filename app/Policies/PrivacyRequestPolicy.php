<?php

namespace App\Policies;

use App\Models\PrivacyRequest;
use App\Models\User;

class PrivacyRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('privacy_requests.view');
    }

    public function view(User $user, PrivacyRequest $request): bool
    {
        if ($request->user_id === $user->id) {
            return true;
        }

        return $user->can('privacy_requests.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PrivacyRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, PrivacyRequest $request): bool
    {
        return false;
    }
}

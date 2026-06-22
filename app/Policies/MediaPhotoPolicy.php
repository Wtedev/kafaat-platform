<?php

namespace App\Policies;

use App\Models\MediaPhoto;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPhotoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function view(User $user, MediaPhoto $photo): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function update(User $user, MediaPhoto $photo): bool
    {
        return $user->hasPermission('manage_media');
    }

    public function delete(User $user, MediaPhoto $photo): bool
    {
        return $user->hasPermission('manage_media');
    }
}

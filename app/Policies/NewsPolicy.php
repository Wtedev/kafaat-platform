<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NewsPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_news') || $user->can('manage_news');
    }

    public function view(User $user, News $news): bool
    {
        return $user->can('view_news') || $user->can('manage_news');
    }

    public function create(User $user): bool
    {
        return $user->can('manage_news');
    }

    public function update(User $user, News $news): bool
    {
        return $user->can('manage_news');
    }

    public function delete(User $user, News $news): bool
    {
        return $user->can('manage_news');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('manage_news');
    }
}

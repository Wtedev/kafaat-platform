<?php

namespace App\Policies;

use App\Models\InboxNotification;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InboxNotificationPolicy
{
    use HandlesAuthorization;

    public function view(User $user, InboxNotification $inboxNotification): bool
    {
        return (int) $inboxNotification->user_id === (int) $user->id;
    }

    public function update(User $user, InboxNotification $inboxNotification): bool
    {
        return $this->view($user, $inboxNotification);
    }
}

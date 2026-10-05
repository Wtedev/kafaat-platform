<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use App\Models\InboxNotification;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffInboxController extends Controller
{
    public function markRead(
        Request $request,
        InboxNotification $notification,
        InboxNotificationService $inbox,
    ): RedirectResponse {
        $actor = $this->actor($request);
        abort_unless((int) $notification->user_id === (int) $actor->id, 403);
        $inbox->markAsRead($notification, $actor);

        return back();
    }

    public function markAllRead(Request $request, InboxNotificationService $inbox): RedirectResponse
    {
        $inbox->markAllAsRead($this->actor($request));

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessFilamentAdmin(), 403);

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\InboxNotification;
use App\Services\Inbox\InboxNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalInboxController extends Controller
{
    public function index(Request $request, InboxNotificationService $inbox): View
    {
        $user = $request->user();
        $items = $inbox->latestForUser($user, 100);
        $unreadCount = $inbox->unreadCount($user);

        return view('portal.notifications.index', compact('items', 'unreadCount'));
    }

    public function markRead(Request $request, InboxNotification $notification, InboxNotificationService $inbox): RedirectResponse
    {
        $this->authorize('update', $notification);

        $inbox->markAsRead($notification, $request->user());

        return back()->with('success', 'تم تعليم التنبيه كمقروء.');
    }

    public function markAllRead(Request $request, InboxNotificationService $inbox): RedirectResponse
    {
        $count = $inbox->markAllAsRead($request->user());
        if ($count === 0) {
            return back()->with('success', 'لا توجد تنبيهات غير مقروءة.');
        }

        return back()->with('success', 'تم تعليم جميع التنبيهات كمقروءة.');
    }
}

<?php

namespace App\Http\Controllers\StaffAlerts;

use App\Http\Controllers\Controller;
use App\Models\StaffAlert;
use App\Models\User;
use App\Services\Inbox\InboxNotificationService;
use App\Services\StaffAlerts\StaffAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Opening, reading and dismissing staff alerts. Works from both the admin panel
 * and the new staff pages.
 */
class StaffAlertController extends Controller
{
    public function __construct(
        private readonly StaffAlerts $alerts,
    ) {}

    public function open(Request $request, StaffAlert $alert): RedirectResponse
    {
        $user = $this->staff($request, $alert);
        $this->alerts->markRead($user, $alert);

        return redirect()->to($this->alerts->linkFor($alert, $user));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $user = $this->staff($request);
        $this->alerts->markAllRead($user);
        app(InboxNotificationService::class)->markAllAsRead($user);

        return back();
    }

    public function dismiss(Request $request, StaffAlert $alert): RedirectResponse
    {
        $user = $this->staff($request, $alert);
        abort_unless($this->alerts->isDismissible($alert), 422);
        $this->alerts->dismiss($user, $alert);

        return back();
    }

    private function staff(Request $request, ?StaffAlert $alert = null): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessFilamentAdmin(), 403);
        if ($alert !== null) {
            abort_unless($this->alerts->canSee($user, $alert), 404);
        }

        return $user;
    }
}

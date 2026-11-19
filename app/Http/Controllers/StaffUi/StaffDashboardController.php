<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffAlerts\StaffAlerts;
use App\Services\StaffUi\StaffDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('staff-ui.dashboard', [
            'staffName' => $user->name ?? '',
            'staffEmail' => $user->email ?? '',
            'alertService' => app(StaffAlerts::class),
            ...app(StaffDashboard::class)->build($user),
        ]);
    }
}

<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('staff-ui.dashboard', [
            'staffName' => $user?->name ?? '',
            'staffEmail' => $user?->email ?? '',
        ]);
    }
}

<?php

namespace App\Http\Controllers\StaffUi;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffUiDemoController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->hasRole('super_admin')),
            403,
        );

        return view('staff-ui.demo', [
            'staffName' => $user->name,
            'staffEmail' => $user->email,
        ]);
    }
}

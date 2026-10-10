<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ProgramAttendanceLink;
use Illuminate\View\View;

class AttendanceDeskController extends Controller
{
    public function show(string $token): View
    {
        abort_unless(
            ProgramAttendanceLink::query()->where('token', $token)->exists(),
            404,
        );

        return view('public.attendance.desk', ['token' => $token]);
    }
}

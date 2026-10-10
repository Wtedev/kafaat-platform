<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProgramAttendanceLink;
use App\Services\Attendance\ProgramAttendanceLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProgramAttendanceLinkCheckInController extends Controller
{
    public function __invoke(Request $request, string $token, ProgramAttendanceLinkService $attendance): RedirectResponse
    {
        $link = ProgramAttendanceLink::query()->where('token', $token)->firstOrFail();
        $attendance->checkIn($link, $request->user());

        return redirect()
            ->route('portal.dashboard')
            ->with('attendance_recorded', $link->name);
    }
}

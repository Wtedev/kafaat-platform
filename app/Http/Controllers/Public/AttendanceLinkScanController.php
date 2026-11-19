<?php

namespace App\Http\Controllers\Public;

use App\Enums\AttendanceLinkMode;
use App\Http\Controllers\Controller;
use App\Models\ProgramAttendanceLink;
use App\Services\Attendance\ProgramAttendanceLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * QR attendance days: the prep officer opens the day's scan link and scans each
 * trainee's attendance QR. The link itself is the credential and only works
 * while the day is open.
 */
class AttendanceLinkScanController extends Controller
{
    public function __construct(
        private readonly ProgramAttendanceLinkService $links,
    ) {}

    public function show(string $token): View
    {
        $link = $this->find($token);

        return view('public.attendance.scan', [
            'link' => $link,
            'open' => $link->isOpen(),
            'count' => $link->marks()->count(),
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $link = $this->find($token);
        $data = $request->validate(['pass' => ['required', 'string', 'max:500']]);

        $result = $this->links->markFromPass($link, $data['pass'], $request->ip());

        return response()->json($result + ['count' => $link->marks()->count()], $result['ok'] ? 200 : 422);
    }

    private function find(string $token): ProgramAttendanceLink
    {
        return ProgramAttendanceLink::query()
            ->with('program:id,title')
            ->where('token', $token)
            ->where('mode', AttendanceLinkMode::Qr->value)
            ->whereNull('cancelled_at')
            ->firstOrFail();
    }
}

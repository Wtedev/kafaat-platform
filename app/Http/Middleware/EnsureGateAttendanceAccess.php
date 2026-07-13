<?php

namespace App\Http\Middleware;

use App\Enums\ProgramDeliveryMode;
use App\Models\ProgramAttendanceChecker;
use App\Models\TrainingProgram;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGateAttendanceAccess
{
    public const SESSION_CHECKER_ID = 'gate_attendance_checker_id';

    public const SESSION_PROGRAM_ID = 'gate_attendance_program_id';

    public function handle(Request $request, Closure $next): Response
    {
        $program = $request->route('program');

        if (! $program instanceof TrainingProgram) {
            abort(404);
        }

        if ($program->delivery_mode !== ProgramDeliveryMode::InPerson) {
            abort(404);
        }

        $user = $request->user();
        if ($user !== null && $user->can('viewOperational', $program)) {
            $request->attributes->set('gate_operator_type', 'admin');
            $request->attributes->set('gate_operator_name', $user->name);
            $request->attributes->set('gate_checker', null);

            return $next($request);
        }

        $checkerId = $request->session()->get(self::SESSION_CHECKER_ID);
        $programId = $request->session()->get(self::SESSION_PROGRAM_ID);

        if ($checkerId && (int) $programId === (int) $program->id) {
            $checker = ProgramAttendanceChecker::query()
                ->whereKey($checkerId)
                ->where('training_program_id', $program->id)
                ->where('is_active', true)
                ->whereNotNull('verified_at')
                ->first();

            if ($checker !== null) {
                $request->attributes->set('gate_operator_type', 'checker');
                $request->attributes->set('gate_operator_name', $checker->name);
                $request->attributes->set('gate_checker', $checker);

                return $next($request);
            }
        }

        return redirect()->route('gate.login', ['program' => $program->slug]);
    }
}

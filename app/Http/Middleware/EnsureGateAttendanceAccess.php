<?php

namespace App\Http\Middleware;

use App\Models\ProgramAttendanceChecker;
use App\Models\TrainingProgram;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGateAttendanceAccess
{
    public const SESSION_CHECKER_ID = 'gate_attendance_checker_id';

    public const SESSION_PROGRAM_ID = 'gate_attendance_program_id';

    public const SESSION_ACCESS_VERSION = 'gate_attendance_access_version';

    public function handle(Request $request, Closure $next): Response
    {
        $program = $request->route('program');

        if (! $program instanceof TrainingProgram) {
            abort(404);
        }

        if (! $this->gateAvailable($program)) {
            abort(404);
        }

        $user = $request->user();
        if (
            $user !== null
            && $user->allowsOperationalAccess()
            && $user->is_active
            && $user->can('viewOperational', $program)
        ) {
            $request->attributes->set('gate_operator_type', 'admin');
            $request->attributes->set('gate_operator_name', $user->name);
            $request->attributes->set('gate_checker', null);

            return $next($request);
        }

        $checkerId = $request->session()->get(self::SESSION_CHECKER_ID);
        $programId = $request->session()->get(self::SESSION_PROGRAM_ID);
        $accessVersion = $request->session()->get(self::SESSION_ACCESS_VERSION);

        if ($checkerId && (int) $programId === (int) $program->id) {
            $checker = ProgramAttendanceChecker::query()
                ->whereKey($checkerId)
                ->where('training_program_id', $program->id)
                ->where('is_active', true)
                ->whereNotNull('access_token_hash')
                ->first();

            if (
                $checker !== null
                && (int) $accessVersion === (int) $checker->access_version
            ) {
                $request->attributes->set('gate_operator_type', 'checker');
                $request->attributes->set('gate_operator_name', $checker->name);
                $request->attributes->set('gate_checker', $checker);

                return $next($request);
            }

            // Stale/invalidated session — clear gate keys only.
            $request->session()->forget([
                self::SESSION_CHECKER_ID,
                self::SESSION_PROGRAM_ID,
                self::SESSION_ACCESS_VERSION,
            ]);
        }

        return redirect()->route('gate.login', ['program' => $program->slug]);
    }

    public static function gateAvailable(TrainingProgram $program): bool
    {
        if ($program->prepDays()->exists()) {
            return true;
        }

        return $program->delivery_mode?->hasPhysicalComponent() === true;
    }
}

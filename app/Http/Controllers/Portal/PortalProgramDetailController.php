<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\AttendanceLiveSessionService;
use Illuminate\Http\Request;

class PortalProgramDetailController extends Controller
{
    public function __invoke(Request $request, TrainingProgram $trainingProgram)
    {
        $user = $request->user();

        $registration = ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->where('training_program_id', $trainingProgram->id)
            ->with(['trainingProgram'])
            ->first();

        abort_if($registration === null, 404);

        $liveSession = app(AttendanceLiveSessionService::class)->activeSessionFor($trainingProgram);

        return view('portal.program-show', [
            'trainingProgram' => $trainingProgram,
            'registration' => $registration,
            'liveSession' => $liveSession,
        ]);
    }
}

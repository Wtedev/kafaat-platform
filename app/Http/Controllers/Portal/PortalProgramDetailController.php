<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalProgramDetailController extends Controller
{
    public function __invoke(Request $request, TrainingProgram $trainingProgram): RedirectResponse
    {
        $user = $request->user();

        $registration = ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->where('training_program_id', $trainingProgram->id)
            ->first();

        abort_if($registration === null, 404);

        if ($request->boolean('attendance') || $request->query('open') === 'attendance') {
            return redirect()->route('portal.programs', [
                'open_attendance' => $trainingProgram->id,
            ]);
        }

        if (filled($trainingProgram->slug)) {
            return redirect()->route('public.programs.show', $trainingProgram->slug);
        }

        return redirect()->route('portal.programs');
    }
}

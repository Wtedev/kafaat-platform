<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Support\Portal\PortalProgramDetailPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalProgramDetailController extends Controller
{
    public function __invoke(
        Request $request,
        TrainingProgram $trainingProgram,
        PortalProgramDetailPresenter $presenter,
    ): View|RedirectResponse {
        $user = $request->user();

        $registration = ProgramRegistration::query()
            ->where('user_id', $user->id)
            ->where('training_program_id', $trainingProgram->id)
            ->with([
                'trainingProgram.prepDays',
                'attendanceRecords',
            ])
            ->first();

        abort_if($registration === null, 404);

        $this->authorize('view', $registration);

        if ($request->boolean('attendance') || $request->query('open') === 'attendance') {
            return redirect()->route('portal.programs', [
                'open_attendance' => $trainingProgram->id,
            ]);
        }

        return view('portal.program-show', $presenter->present($registration, $user));
    }
}

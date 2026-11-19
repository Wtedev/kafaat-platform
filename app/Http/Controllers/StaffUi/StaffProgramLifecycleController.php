<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\ProgramActivityAction;
use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramActivity\ProgramActivityLog;
use App\Services\StaffUi\StaffProgramCopier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Copy a program, move it to the trash, or bring it back. Nothing here removes
 * a program for good.
 */
class StaffProgramLifecycleController extends Controller
{
    public function __construct(
        private readonly ProgramActivityLog $activity,
    ) {}

    public function copy(Request $request, TrainingProgram $program, StaffProgramCopier $copier): RedirectResponse
    {
        $actor = $this->actorFor($request);
        abort_unless($actor->can('duplicate', $program), 403);

        $copy = $copier->copy($program, $actor);

        return redirect()
            ->route('staff-ui.programs.tab', [$copy, 'settings'])
            ->with('status', 'نُسخ البرنامج كمسودة. حدّثي العنوان والمواعيد ثم انشريه.');
    }

    public function destroy(Request $request, TrainingProgram $program): RedirectResponse
    {
        $actor = $this->actorFor($request);
        abort_unless($actor->can('delete', $program), 403);

        $program->delete();
        $this->activity->record($program, ProgramActivityAction::Deleted, null, null, [], $actor);

        return redirect()
            ->route('staff-ui.programs.index', ['view' => 'trash'])
            ->with('status', 'نُقل «'.$program->title.'» إلى المحذوفات. تقدرين تستعيدينه في أي وقت.');
    }

    public function restore(Request $request, int $program): RedirectResponse
    {
        $actor = $this->actorFor($request);
        $model = TrainingProgram::onlyTrashed()->findOrFail($program);
        abort_unless($actor->can('restore', $model), 403);

        $model->restore();
        $this->activity->record($model, ProgramActivityAction::Undeleted, null, null, [], $actor);

        return redirect()
            ->route('staff-ui.programs.show', $model)
            ->with('status', 'رجع البرنامج من المحذوفات بكل بياناته.');
    }

    private function actorFor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }
}

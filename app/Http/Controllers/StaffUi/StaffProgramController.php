<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\StaffUi\StaffProgramIndex;
use App\Services\StaffUi\StaffProgramStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffProgramController extends Controller
{
    public function index(Request $request, StaffProgramIndex $index, StaffProgramStatus $status): View
    {
        $this->ensureStaff($request);
        $this->authorize('viewAny', TrainingProgram::class);

        $search = trim((string) $request->query('q', ''));
        $kind = TrainingProgramKind::tryFrom(trim((string) $request->query('kind', '')))?->value ?? '';
        $programStatus = ProgramStatus::tryFrom(trim((string) $request->query('status', '')))?->value ?? '';
        $programs = $index->paginate($search, $kind, $programStatus);
        $user = $request->user();

        return view('staff-ui.programs.index', [
            'staffName' => $user->name,
            'staffEmail' => $user->email,
            'programs' => $programs,
            'search' => $search,
            'kind' => $kind,
            'status' => $programStatus,
            'kindOptions' => ['' => 'كل الأنواع'] + TrainingProgramKind::options(),
            'statusOptions' => [
                '' => 'كل الحالات',
                ProgramStatus::Draft->value => ProgramStatus::Draft->label(),
                ProgramStatus::Published->value => ProgramStatus::Published->label(),
                ProgramStatus::Archived->value => ProgramStatus::Archived->label(),
            ],
            'filtersActive' => $search !== '' || $kind !== '' || $programStatus !== '',
            'statusService' => $status,
        ]);
    }

    public function show(Request $request, TrainingProgram $program): View
    {
        $this->ensureStaff($request);
        $this->authorize('view', $program);

        return view('staff-ui.programs.show', [
            'staffName' => $request->user()->name,
            'staffEmail' => $request->user()->email,
            'program' => $program,
        ]);
    }

    private function ensureStaff(Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);
    }
}

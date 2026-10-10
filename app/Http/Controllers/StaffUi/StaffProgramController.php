<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\ProgramStatus;
use App\Enums\StaffUi\StaffRegistrationAvailability;
use App\Enums\TrainingProgramKind;
use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\StaffUi\StaffProgramIndex;
use App\Services\StaffUi\StaffProgramStatus;
use App\Services\StaffUi\StaffProgramWizard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffProgramController extends Controller
{
    public function index(Request $request, StaffProgramIndex $index, StaffProgramStatus $status): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('viewAny', TrainingProgram::class), 403);

        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $kind = (string) $request->query('kind', '');
        $programStatus = (string) $request->query('status', '');
        $registration = (string) $request->query('registration', '');

        if (TrainingProgramKind::tryFrom($kind) === null) {
            $kind = '';
        }

        if (ProgramStatus::tryFrom($programStatus) === null) {
            $programStatus = '';
        }

        $allowedRegistration = [
            StaffRegistrationAvailability::Open->value,
            StaffRegistrationAvailability::Closed->value,
            StaffRegistrationAvailability::NotStarted->value,
            StaffRegistrationAvailability::Full->value,
            StaffRegistrationAvailability::Path->value,
        ];
        if (! in_array($registration, $allowedRegistration, true)) {
            $registration = '';
        }

        $programs = $index->paginate(
            $search,
            $kind,
            $programStatus,
            $registration,
            (int) $request->query('page', 1),
        );

        $filtersActive = $search !== '' || $kind !== '' || $programStatus !== '' || $registration !== '';

        return view('staff-ui.programs.index', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'programs' => $programs,
            'search' => $search,
            'kind' => $kind,
            'status' => $programStatus,
            'registration' => $registration,
            'filtersActive' => $filtersActive,
            'kindOptions' => ['' => 'الكل', ...TrainingProgramKind::options()],
            'statusOptions' => [
                '' => 'الكل',
                ProgramStatus::Draft->value => ProgramStatus::Draft->label(),
                ProgramStatus::Published->value => ProgramStatus::Published->label(),
                ProgramStatus::Archived->value => ProgramStatus::Archived->label(),
            ],
            'registrationOptions' => [
                '' => 'الكل',
                StaffRegistrationAvailability::NotStarted->value => StaffRegistrationAvailability::NotStarted->label(),
                StaffRegistrationAvailability::Closed->value => StaffRegistrationAvailability::Closed->label(),
                StaffRegistrationAvailability::Full->value => StaffRegistrationAvailability::Full->label(),
                StaffRegistrationAvailability::Open->value => StaffRegistrationAvailability::Open->label(),
                StaffRegistrationAvailability::Path->value => StaffRegistrationAvailability::Path->label(),
            ],
            'programStatus' => $status,
            'canCreate' => $actor->can('create', TrainingProgram::class),
        ]);
    }

    public function show(Request $request, TrainingProgram $program): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('view', $program), 403);

        return view('staff-ui.programs.show', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'program' => $program,
            'canResumeWizard' => $program->status === ProgramStatus::Draft
                && app(StaffProgramWizard::class)->canContinue($actor, $program),
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }
}

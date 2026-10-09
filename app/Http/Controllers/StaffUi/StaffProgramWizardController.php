<?php

namespace App\Http\Controllers\StaffUi;

use App\Enums\CompetencyTrack;
use App\Enums\ProfileGender;
use App\Enums\ProgramDeliveryMode;
use App\Enums\TrainingProgramKind;
use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\StaffUi\StaffProgramWizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffProgramWizardController extends Controller
{
    public function create(Request $request): View
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('create', TrainingProgram::class), 403);

        return $this->wizardView($actor, null, 1);
    }

    public function show(Request $request, TrainingProgram $program, int $step): View|RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->wizard->canContinue($actor, $program), 403);
        $step = $this->step($step);

        if ($step === 6 && $program->title === null) {
            return redirect()->route('staff-ui.programs.wizard', [$program, 1]);
        }

        return $this->wizardView($actor, $program, $step);
    }

    public function storeNew(Request $request, StaffProgramWizard $wizard): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('create', TrainingProgram::class), 403);

        $program = $wizard->createDraft($actor, $request->all(), $request->file('image'));
        $request->session()->put($this->intentKey($program), [
            'publish' => false,
            'notify' => true,
        ]);

        return redirect()->route('staff-ui.programs.wizard', [$program, 2]);
    }

    public function store(Request $request, TrainingProgram $program, int $step, StaffProgramWizard $wizard): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($wizard->canContinue($actor, $program), 403);
        $step = $this->step($step);

        if ($step === 6) {
            try {
                $wizard->finish($actor, $program, $request->all());
            } catch (ValidationException $exception) {
                $invalid = isset($exception->errors()['publish'])
                    ? 5
                    : ($wizard->firstInvalidStoredStep($program->refresh()) ?? 1);

                return redirect()
                    ->route('staff-ui.programs.wizard', [$program, $invalid])
                    ->withErrors($exception->errors());
            }

            $request->session()->forget($this->intentKey($program));

            return redirect()
                ->route('staff-ui.programs.show', $program)
                ->with('status', $program->status->value === 'published' ? 'تم نشر البرنامج.' : 'تم حفظ البرنامج كمسودة.');
        }

        $wizard->saveStep($program, $step, $request->all(), $request->file('image'));

        if ($step === 5) {
            $request->session()->put($this->intentKey($program), [
                'publish' => $request->boolean('publish'),
                'notify' => $request->boolean('notify_audience'),
            ]);
        }

        $goto = (int) $request->input('goto', 0);
        $next = $goto >= 1 && $goto <= 6 ? $goto : ($step + 1);

        return redirect()->route('staff-ui.programs.wizard', [$program, min(6, $next)]);
    }

    public function previewMail(Request $request, TrainingProgram $program, StaffProgramWizard $wizard): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($wizard->canContinue($actor, $program), 403);

        $wizard->sendPreview($actor, $program, $request->all());

        return redirect()
            ->route('staff-ui.programs.wizard', [$program, 4])
            ->with('status', 'أُرسلت نسخة القبول إلى بريدك.');
    }

    public function preview(Request $request, TrainingProgram $program): View
    {
        $actor = $this->actor($request);
        abort_unless($this->wizard->canContinue($actor, $program), 403);

        return view('public.programs.show', [
            'trainingProgram' => $program,
            'userRegistration' => null,
            'acceptanceEvaluation' => null,
            'staffPreview' => true,
        ]);
    }

    public function acceptancePreview(Request $request, TrainingProgram $program): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->wizard->canContinue($actor, $program), 403);

        return response()->json([
            'lines' => $this->wizard->acceptanceLinesFromInput($request->all()),
        ]);
    }

    public function __construct(
        private readonly StaffProgramWizard $wizard,
    ) {}

    private function wizardView(User $actor, ?TrainingProgram $program, int $step): View
    {
        $intent = $program instanceof TrainingProgram
            ? $this->intent($program)
            : ['publish' => false, 'notify' => true];

        return view('staff-ui.programs.wizard', [
            'staffName' => $actor->name,
            'staffEmail' => $actor->email,
            'program' => $program,
            'step' => $step,
            'kindOptions' => TrainingProgramKind::options(),
            'trackOptions' => CompetencyTrack::shortOptions(),
            'deliveryOptions' => ProgramDeliveryMode::options(),
            'genderOptions' => ProfileGender::options(),
            'weekdayOptions' => [
                0 => 'الأحد',
                1 => 'الإثنين',
                2 => 'الثلاثاء',
                3 => 'الأربعاء',
                4 => 'الخميس',
                5 => 'الجمعة',
                6 => 'السبت',
            ],
            'publishIntent' => (bool) $intent['publish'],
            'notifyIntent' => (bool) $intent['notify'],
            'acceptancePreview' => $program instanceof TrainingProgram ? $this->wizard->acceptancePreview($program) : [],
            'canPublish' => $program instanceof TrainingProgram && $actor->can('publish', $program),
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessFilamentAdmin(), 403);

        return $actor;
    }

    private function step(int $step): int
    {
        abort_unless(in_array($step, StaffProgramWizard::STEPS, true), 404);

        return $step;
    }

    private function intentKey(TrainingProgram $program): string
    {
        return 'staff_program_wizard.'.$program->id;
    }

    /**
     * @return array{publish: bool, notify: bool}
     */
    private function intent(TrainingProgram $program): array
    {
        $stored = session($this->intentKey($program), []);

        return [
            'publish' => (bool) ($stored['publish'] ?? false),
            'notify' => (bool) ($stored['notify'] ?? ($program->notify_on_publish && $program->notify_milestones && $program->notify_registrants_on_update)),
        ];
    }
}

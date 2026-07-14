<?php

namespace App\Http\Controllers\Public;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Exceptions\ProgramBelongsToLearningPathException;
use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationNotEligibleException;
use App\Exceptions\RegistrationWindowClosedException;
use App\Http\Controllers\Controller;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\ProgramAcceptanceConditionEvaluator;
use App\Services\ProgramRegistrationService;
use App\Support\ProgramAcceptanceConditions;
use App\Support\ProgramRegistrationSuccessPresenter;
use Illuminate\Http\Request;

class PublicTrainingProgramController extends Controller
{
    public function __construct(
        private readonly ProgramRegistrationService $registrationService,
        private readonly ProgramAcceptanceConditionEvaluator $acceptanceEvaluator,
    ) {}

    public function index()
    {
        return redirect()->route('public.programs.track', CompetencyTrack::Self);
    }

    public function track(CompetencyTrack $track)
    {
        $meta = config('competency_tracks.tracks.'.$track->value, []);

        $programs = TrainingProgram::published()
            ->standaloneCatalog()
            ->forCompetencyTrack($track)
            ->latest('published_at')
            ->paginate(12);

        return view('public.programs.track', [
            'track' => $track,
            'meta' => $meta,
            'programs' => $programs,
        ]);
    }

    public function show(TrainingProgram $trainingProgram)
    {
        abort_if($trainingProgram->status->value !== 'published', 404);

        $userRegistration = null;
        $acceptanceEvaluation = null;
        if (auth()->check()) {
            $user = auth()->user();
            $userRegistration = $trainingProgram->registrations()
                ->where('user_id', $user->id)
                ->latest()
                ->first();

            if ($userRegistration === null
                && $trainingProgram->learning_path_id === null
                && ProgramAcceptanceConditions::hasAny(
                    is_array($trainingProgram->acceptance_conditions) ? $trainingProgram->acceptance_conditions : null
                )) {
                $acceptanceEvaluation = $this->acceptanceEvaluator->evaluate($trainingProgram, $user);
            }
        }

        if ($trainingProgram->learning_path_id !== null) {
            $trainingProgram->loadMissing('learningPath');
        }

        return view('public.programs.show', compact('trainingProgram', 'userRegistration', 'acceptanceEvaluation'));
    }

    public function register(Request $request, TrainingProgram $trainingProgram)
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->canRegisterForPublicOfferings()) {
            abort(403);
        }

        $inPerson = $trainingProgram->delivery_mode === ProgramDeliveryMode::InPerson;

        $request->validate(
            [
                'attendance_acknowledgement' => ['accepted'],
            ],
            [
                'attendance_acknowledgement.accepted' => $inPerson
                    ? 'يلزم الإقرار بأنك قرأت تفاصيل البرنامج وتعرف موقع انعقاده وتستطيع الحضور.'
                    : 'يلزم الإقرار بأنك قرأت جميع تفاصيل البرنامج.',
            ],
        );

        try {
            $registration = $this->registrationService->register($trainingProgram, $request->user());

            return redirect()->route('public.programs.registered', [
                'trainingProgram' => $trainingProgram->slug,
                'registration' => $registration->getKey(),
            ]);
        } catch (ProgramBelongsToLearningPathException) {
            return back()->with('error', 'هذا البرنامج ضمن مسار تعليمي؛ التسجيل يكون من صفحة المسار فقط.');
        } catch (RegistrationWindowClosedException) {
            return back()->with('error', 'باب التسجيل في هذا البرنامج مغلق حالياً.');
        } catch (ProgramCapacityExceededException) {
            return back()->with('error', 'البرنامج وصل إلى الحد الأقصى للمشتركين.');
        } catch (RegistrationNotEligibleException $e) {
            return back()->with('error', $e->userMessage());
        }
    }

    public function registered(Request $request, TrainingProgram $trainingProgram, ProgramRegistration $registration)
    {
        abort_if($trainingProgram->status->value !== 'published', 404);
        abort_unless((int) $registration->training_program_id === (int) $trainingProgram->id, 404);
        abort_unless($request->user() !== null && (int) $request->user()->id === (int) $registration->user_id, 403);

        $registration->loadMissing(['trainingProgram', 'user.profile']);
        $success = ProgramRegistrationSuccessPresenter::present(
            $trainingProgram,
            $registration,
            $request->user(),
        );

        return view('public.programs.registered', [
            'trainingProgram' => $trainingProgram,
            'registration' => $registration,
            'success' => $success,
        ]);
    }
}

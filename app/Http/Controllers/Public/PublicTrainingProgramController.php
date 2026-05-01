<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationWindowClosedException;
use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use App\Services\ProgramRegistrationService;
use Illuminate\Http\Request;

class PublicTrainingProgramController extends Controller
{
    public function __construct(
        private readonly ProgramRegistrationService $registrationService,
    ) {}

    public function index()
    {
        $programs = TrainingProgram::published()
            ->latest('published_at')
            ->paginate(12);

        return view('public.programs.index', compact('programs'));
    }

    public function show(TrainingProgram $trainingProgram)
    {
        abort_if($trainingProgram->status->value !== 'published', 404);

        $userRegistration = null;
        if (auth()->check()) {
            $userRegistration = $trainingProgram->registrations()
                ->where('user_id', auth()->id())
                ->latest()
                ->first();
        }

        return view('public.programs.show', compact('trainingProgram', 'userRegistration'));
    }

    public function register(Request $request, TrainingProgram $trainingProgram)
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if ($request->user()->role_type !== 'beneficiary') {
            abort(403);
        }

        try {
            $this->registrationService->register($trainingProgram, $request->user());

            return back()->with('success', 'تم تسجيلك بنجاح! سيتم مراجعة طلبك قريباً.');
        } catch (RegistrationWindowClosedException) {
            return back()->with('error', 'باب التسجيل في هذا البرنامج مغلق حالياً.');
        } catch (ProgramCapacityExceededException) {
            return back()->with('error', 'البرنامج وصل إلى الحد الأقصى للمشتركين.');
        }
    }
}

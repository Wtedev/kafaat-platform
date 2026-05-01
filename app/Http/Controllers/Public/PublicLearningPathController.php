<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\DuplicateRegistrationException;
use App\Exceptions\PathCapacityExceededException;
use App\Exceptions\PathNotPublishedException;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Services\PathRegistrationService;
use Illuminate\Http\Request;

class PublicLearningPathController extends Controller
{
    public function __construct(
        private readonly PathRegistrationService $registrationService,
    ) {}

    public function index()
    {
        $paths = LearningPath::published()
            ->latest('published_at')
            ->paginate(12);

        return view('public.paths.index', compact('paths'));
    }

    public function show(LearningPath $learningPath)
    {
        abort_if($learningPath->status->value !== 'published', 404);

        $learningPath->load('courses');

        $userRegistration = null;
        if (auth()->check()) {
            $userRegistration = $learningPath->registrations()
                ->where('user_id', auth()->id())
                ->latest()
                ->first();
        }

        return view('public.paths.show', compact('learningPath', 'userRegistration'));
    }

    public function register(Request $request, LearningPath $learningPath)
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->isPortalUser()) {
            abort(403);
        }

        try {
            $this->registrationService->register($request->user(), $learningPath);

            return back()->with('success', 'تم تسجيلك بنجاح! سيتم مراجعة طلبك قريباً.');
        } catch (DuplicateRegistrationException) {
            return back()->with('error', 'لديك تسجيل نشط بالفعل في هذا المسار.');
        } catch (PathNotPublishedException) {
            return back()->with('error', 'هذا المسار غير متاح للتسجيل حالياً.');
        } catch (PathCapacityExceededException) {
            return back()->with('error', 'المسار وصل إلى الحد الأقصى للمشتركين.');
        }
    }
}

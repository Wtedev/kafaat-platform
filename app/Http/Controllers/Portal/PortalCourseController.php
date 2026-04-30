<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ProgressStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCourseProgressRequest;
use App\Models\LearningPath;
use App\Models\PathCourse;
use App\Models\PathRegistration;
use App\Models\UserCourseProgress;
use App\Services\ProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalCourseController extends Controller
{
    public function __construct(
        private readonly ProgressService $progressService,
    ) {}

    // ─── Pages ───────────────────────────────────────────────────────────────

    /**
     * List all published courses in a path (requires approved or completed registration).
     */
    public function index(Request $request, LearningPath $learningPath)
    {
        $user         = $request->user();
        $registration = $this->getApprovedRegistration($user->id, $learningPath);

        if ($registration === null) {
            return redirect()->route('portal.paths')
                ->with('error', 'لا يمكنك الوصول للدورات إلا بعد قبولك في المسار.');
        }

        $courses      = $learningPath->courses()->accessible()->orderBy('sort_order')->get();
        $progressMap  = $learningPath->getUserProgress($user);
        $pathProgress = $this->progressService->calculatePathProgress($user, $learningPath);
        $total        = $this->progressService->getTotalRequiredCoursesCount($learningPath);
        $completed    = $this->progressService->getCompletedCoursesCount($user, $learningPath);

        return view('portal.courses', compact(
            'learningPath', 'courses', 'progressMap',
            'pathProgress', 'total', 'completed', 'registration'
        ));
    }

    /**
     * Show a single course's content page.
     */
    public function show(Request $request, LearningPath $learningPath, PathCourse $pathCourse)
    {
        // Ensure course belongs to this path
        abort_if($pathCourse->learning_path_id !== $learningPath->id, 404);

        // Only published courses are accessible
        abort_if($pathCourse->status->value !== 'published', 404);

        $user         = $request->user();
        $registration = $this->getApprovedRegistration($user->id, $learningPath);

        if ($registration === null) {
            return redirect()->route('portal.paths')
                ->with('error', 'لا يمكنك الوصول للدورات إلا بعد قبولك في المسار.');
        }

        // Ensure progress row exists (lazy creation in NotStarted state)
        $progress = UserCourseProgress::firstOrCreate(
            [
                'user_id'        => $user->id,
                'path_course_id' => $pathCourse->id,
            ],
            [
                'progress_percentage' => 0.0,
                'status'              => ProgressStatus::NotStarted,
            ]
        );

        return view('portal.course', compact(
            'learningPath', 'pathCourse', 'progress', 'registration'
        ));
    }

    // ─── Actions ─────────────────────────────────────────────────────────────

    /**
     * POST /portal/courses/{pathCourse}/start
     * Mark a course as started and redirect to its detail page.
     */
    public function start(Request $request, PathCourse $pathCourse): RedirectResponse
    {
        $user         = $request->user();
        $learningPath = $pathCourse->learningPath;

        $this->requireApprovedRegistration($user->id, $learningPath);
        abort_if($pathCourse->status->value !== 'published', 403);

        $this->progressService->startCourse($user, $pathCourse);

        return redirect()->route('portal.paths.courses.show', [$learningPath, $pathCourse]);
    }

    /**
     * POST /portal/courses/{pathCourse}/progress
     * Update course progress percentage and optional score.
     */
    public function progress(UpdateCourseProgressRequest $request, PathCourse $pathCourse): RedirectResponse
    {
        $user         = $request->user();
        $learningPath = $pathCourse->learningPath;

        $this->requireApprovedRegistration($user->id, $learningPath);
        abort_if($pathCourse->status->value !== 'published', 403);

        $this->progressService->updateCourseProgress(
            $user,
            $pathCourse,
            (float) $request->validated('progress_percentage'),
            $request->filled('score') ? (float) $request->validated('score') : null,
        );

        return back()->with('success', 'تم تحديث التقدم بنجاح.');
    }

    /**
     * POST /portal/courses/{pathCourse}/complete
     * Mark a course as 100% completed and trigger path completion check.
     */
    public function complete(Request $request, PathCourse $pathCourse): RedirectResponse
    {
        $user         = $request->user();
        $learningPath = $pathCourse->learningPath;

        $this->requireApprovedRegistration($user->id, $learningPath);
        abort_if($pathCourse->status->value !== 'published', 403);

        $wasPathCompleted = $this->progressService->isPathCompleted($user, $learningPath);

        $this->progressService->completeCourse($user, $pathCourse);

        $isPathNowCompleted = $this->progressService->isPathCompleted($user, $learningPath);

        if (! $wasPathCompleted && $isPathNowCompleted) {
            return redirect()
                ->route('portal.paths.courses', $learningPath)
                ->with('path_completed', 'تهانينا! تم إكمال المسار التعليمي وإصدار الشهادة. يمكنك الآن تحميلها من صفحة الشهادات.');
        }

        return back()->with('success', 'تم إنهاء الدورة بنجاح!');
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Return the active approved/completed registration or null.
     */
    private function getApprovedRegistration(int $userId, LearningPath $path): ?PathRegistration
    {
        return PathRegistration::where('user_id', $userId)
            ->where('learning_path_id', $path->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->first();
    }

    /**
     * Abort with 403 if no approved/completed registration found.
     */
    private function requireApprovedRegistration(int $userId, LearningPath $path): void
    {
        abort_if(
            $this->getApprovedRegistration($userId, $path) === null,
            403,
            'لا يمكنك الوصول للدورات إلا بعد قبولك في المسار.'
        );
    }
}

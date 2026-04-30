<?php

namespace App\Services;

use App\Enums\CourseStatus;
use App\Enums\ProgressStatus;
use App\Enums\RegistrationStatus;
use App\Models\LearningPath;
use App\Models\PathCourse;
use App\Models\PathRegistration;
use App\Models\User;
use App\Models\UserCourseProgress;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    public function __construct(
        private readonly CertificateService $certificateService,
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Start a course for a user (creates a progress row in InProgress state).
     * If already started or completed, returns existing row without changes.
     */
    public function startCourse(User $user, PathCourse $course): UserCourseProgress
    {
        $progress = UserCourseProgress::firstOrCreate(
            [
                'user_id'        => $user->id,
                'path_course_id' => $course->id,
            ],
            [
                'progress_percentage' => 0.0,
                'status'              => ProgressStatus::InProgress,
            ]
        );

        if ($progress->status === ProgressStatus::NotStarted) {
            $progress->update(['status' => ProgressStatus::InProgress]);
        }

        return $progress->fresh();
    }

    /**
     * Mark a course as fully completed (100%, status = completed, completed_at = now).
     * Then checks whether the parent path is now eligible for completion.
     */
    public function completeCourse(User $user, PathCourse $course): UserCourseProgress
    {
        $progress = UserCourseProgress::updateOrCreate(
            [
                'user_id'        => $user->id,
                'path_course_id' => $course->id,
            ],
            [
                'progress_percentage' => 100.00,
                'status'              => ProgressStatus::Completed,
                'completed_at'        => now(),
            ]
        );

        $this->completePathIfEligible($user, $course->learningPath);

        return $progress->fresh();
    }

    /**
     * Record or update a user's progress on a single course.
     * Status is derived automatically from percentage.
     * After updating, checks if the parent learning path is now fully completed.
     */
    public function updateCourseProgress(
        User       $user,
        PathCourse $course,
        float      $percentage,
        ?float     $score = null,
    ): UserCourseProgress {
        $percentage = max(0.0, min(100.0, $percentage));

        $status = match (true) {
            $percentage >= 100.0 => ProgressStatus::Completed,
            $percentage > 0.0    => ProgressStatus::InProgress,
            default              => ProgressStatus::NotStarted,
        };

        $attributes = [
            'progress_percentage' => $percentage,
            'status'              => $status,
        ];

        if ($score !== null) {
            $attributes['score'] = $score;
        }

        // Only set completed_at the first time the course reaches 100%
        if ($status === ProgressStatus::Completed) {
            $existing = UserCourseProgress::where('user_id', $user->id)
                ->where('path_course_id', $course->id)
                ->first();

            if ($existing === null || $existing->completed_at === null) {
                $attributes['completed_at'] = now();
            }
        }

        $progress = UserCourseProgress::updateOrCreate(
            [
                'user_id'        => $user->id,
                'path_course_id' => $course->id,
            ],
            $attributes
        );

        $this->completePathIfEligible($user, $course->learningPath);

        return $progress->fresh();
    }

    /**
     * Calculate average progress across all required published courses in a path.
     * Courses with no progress row are treated as 0%.
     * Returns 0.0–100.0.
     */
    public function calculatePathProgress(User $user, LearningPath $path): float
    {
        $courseIds = $this->getRequiredPublishedCourseIds($path);

        if ($courseIds->isEmpty()) {
            return 0.0;
        }

        $progressRows = UserCourseProgress::where('user_id', $user->id)
            ->whereIn('path_course_id', $courseIds)
            ->pluck('progress_percentage', 'path_course_id');

        $total = $courseIds->reduce(
            fn (float $carry, int $id) => $carry + (float) ($progressRows[$id] ?? 0.0),
            0.0
        );

        return round($total / $courseIds->count(), 2);
    }

    /**
     * Count how many required published courses the user has completed in a path.
     */
    public function getCompletedCoursesCount(User $user, LearningPath $path): int
    {
        $courseIds = $this->getRequiredPublishedCourseIds($path);

        if ($courseIds->isEmpty()) {
            return 0;
        }

        return UserCourseProgress::where('user_id', $user->id)
            ->whereIn('path_course_id', $courseIds)
            ->where('status', ProgressStatus::Completed->value)
            ->count();
    }

    /**
     * Count total required published courses in a path.
     */
    public function getTotalRequiredCoursesCount(LearningPath $path): int
    {
        return $path->courses()
            ->where('status', CourseStatus::Published->value)
            ->where('is_required', true)
            ->count();
    }

    /**
     * Return true if the user has completed ALL required published courses.
     * A path with no required published courses is NOT considered complete.
     */
    public function isPathCompleted(User $user, LearningPath $path): bool
    {
        $courseIds = $this->getRequiredPublishedCourseIds($path);

        if ($courseIds->isEmpty()) {
            return false;
        }

        $completedCount = UserCourseProgress::where('user_id', $user->id)
            ->whereIn('path_course_id', $courseIds)
            ->where('status', ProgressStatus::Completed->value)
            ->count();

        return $completedCount === $courseIds->count();
    }

    /**
     * If the user has completed all required published courses:
     * - mark the PathRegistration status → Completed
     * - set completed_at
     * - issue certificate (idempotent — no duplicates)
     */
    public function completePathIfEligible(User $user, LearningPath $path): void
    {
        if (! $this->isPathCompleted($user, $path)) {
            return;
        }

        $registration = PathRegistration::where('user_id', $user->id)
            ->where('learning_path_id', $path->id)
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->first();

        if ($registration === null) {
            return;
        }

        DB::transaction(function () use ($registration, $user, $path) {
            if ($registration->status !== RegistrationStatus::Completed) {
                $registration->update([
                    'status'       => RegistrationStatus::Completed,
                    'completed_at' => now(),
                ]);
            }

            $this->certificateService->issue($user, $path);
        });
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * @return \Illuminate\Support\Collection<int>
     */
    private function getRequiredPublishedCourseIds(LearningPath $path): \Illuminate\Support\Collection
    {
        return $path->courses()
            ->where('status', CourseStatus::Published->value)
            ->where('is_required', true)
            ->pluck('id');
    }
}

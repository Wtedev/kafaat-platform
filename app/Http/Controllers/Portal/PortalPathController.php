<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class PortalPathController extends Controller
{
    public function __construct(
        private readonly ProgressService $progressService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();

        $registrations = $user->learningPathRegistrations()
            ->with('learningPath')
            ->latest()
            ->paginate(15);

        foreach ($registrations as $registration) {
            $path = $registration->learningPath;
            if ($path) {
                $registration->progress_percentage = $this->progressService->calculatePathProgress($user, $path);
                $registration->total_courses = $this->progressService->getTotalRequiredCoursesCount($path);
                $registration->completed_courses = $this->progressService->getCompletedCoursesCount($user, $path);
            } else {
                $registration->progress_percentage = 0.0;
                $registration->total_courses = 0;
                $registration->completed_courses = 0;
            }
        }

        return view('portal.paths', compact('registrations'));
    }
}

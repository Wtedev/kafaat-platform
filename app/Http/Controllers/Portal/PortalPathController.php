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
                $registration->total_programs = $this->progressService->getTotalProgramsInPathCount($path);
                $registration->completed_programs = $this->progressService->getCompletedProgramsCount($user, $path);
            } else {
                $registration->progress_percentage = 0.0;
                $registration->total_programs = 0;
                $registration->completed_programs = 0;
            }
        }

        return view('portal.paths', compact('registrations'));
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class PortalPathDetailController extends Controller
{
    public function __construct(
        private readonly ProgressService $progressService,
    ) {}

    public function __invoke(Request $request, LearningPath $learningPath)
    {
        $user = $request->user();

        $registration = PathRegistration::query()
            ->where('user_id', $user->id)
            ->where('learning_path_id', $learningPath->id)
            ->first();

        abort_if($registration === null, 404);

        $learningPath->load([
            'programs' => fn ($q) => $q->published()
                ->orderByRaw('path_sort_order IS NULL')
                ->orderBy('path_sort_order')
                ->orderBy('id'),
        ]);

        $programRows = $learningPath->programs->map(function ($program) use ($user) {
            $reg = $program->registrations()->where('user_id', $user->id)->first();

            return [
                'program' => $program,
                'registration' => $reg,
                'progress' => $this->progressService->programProgressPercentage($user, $program),
            ];
        });

        $pathProgress = $registration->canAccessPathPrograms()
            ? $this->progressService->calculatePathProgress($user, $learningPath)
            : null;

        return view('portal.path-show', [
            'learningPath' => $learningPath,
            'registration' => $registration,
            'programRows' => $programRows,
            'pathProgress' => $pathProgress,
        ]);
    }
}

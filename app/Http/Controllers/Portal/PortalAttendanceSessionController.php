<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLiveSession;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Services\AttendanceLiveSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalAttendanceSessionController extends Controller
{
    public function program(Request $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $registration = ProgramRegistration::query()
            ->where('user_id', $request->user()->id)
            ->where('training_program_id', $trainingProgram->id)
            ->first();

        abort_if($registration === null, 404);

        return response()->json(
            $this->sessionPayload(
                $registration->isApproved() || $registration->isCompleted(),
                app(AttendanceLiveSessionService::class)->activeSessionFor($trainingProgram),
            ),
        );
    }

    public function path(Request $request, LearningPath $learningPath): JsonResponse
    {
        $registration = PathRegistration::query()
            ->where('user_id', $request->user()->id)
            ->where('learning_path_id', $learningPath->id)
            ->first();

        abort_if($registration === null, 404);

        return response()->json(
            $this->sessionPayload(
                $registration->canAccessPathPrograms(),
                app(AttendanceLiveSessionService::class)->activeSessionFor($learningPath),
            ),
        );
    }

    /**
     * @return array{active: bool, expires_at_ms: int|null, remaining_seconds: int}
     */
    private function sessionPayload(bool $canCheckIn, ?AttendanceLiveSession $session): array
    {
        $active = $canCheckIn && $session !== null && $session->isActive();

        return [
            'active' => $active,
            'expires_at_ms' => $active ? $session->expires_at->getTimestamp() * 1000 : null,
            'remaining_seconds' => $active ? $session->remainingSeconds() : 0,
        ];
    }
}

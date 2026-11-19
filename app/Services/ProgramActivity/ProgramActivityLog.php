<?php

namespace App\Services\ProgramActivity;

use App\Enums\ProgramActivityAction;
use App\Models\ProgramActivity;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Writes the per-program activity log. Logging must never break the action it
 * describes, so failures are reported and swallowed.
 */
final class ProgramActivityLog
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        TrainingProgram|int $program,
        ProgramActivityAction $action,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
        ?User $actor = null,
    ): ?ProgramActivity {
        $programId = $program instanceof TrainingProgram ? (int) $program->getKey() : $program;
        if ($programId <= 0) {
            return null;
        }

        $actor ??= Auth::user() instanceof User ? Auth::user() : null;

        try {
            return ProgramActivity::query()->create([
                'training_program_id' => $programId,
                'actor_id' => $actor?->getKey(),
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'description' => $description !== null ? mb_substr($description, 0, 500) : null,
                'properties' => $properties === [] ? null : $properties,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}

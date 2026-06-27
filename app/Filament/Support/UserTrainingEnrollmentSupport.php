<?php

namespace App\Filament\Support;

use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\TrainingProgramResource;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Support\Collection;

final class UserTrainingEnrollmentSupport
{
    /**
     * @return Collection<int|string, array<string, mixed>>
     */
    public static function recordsFor(User $user): Collection
    {
        $user->loadMissing([
            'learningPathRegistrations.learningPath',
            'programRegistrations.trainingProgram.learningPath',
        ]);

        $rows = collect();

        foreach ($user->learningPathRegistrations as $registration) {
            $rows->put(
                'path-'.$registration->getKey(),
                self::pathRow($registration),
            );
        }

        foreach ($user->programRegistrations as $registration) {
            $rows->put(
                'program-'.$registration->getKey(),
                self::programRow($registration),
            );
        }

        return $rows->sortByDesc(fn (array $row): int => $row['sort_at']->getTimestamp())->values();
    }

    /**
     * @return array<string, mixed>
     */
    private static function pathRow(PathRegistration $registration): array
    {
        return [
            'id' => 'path-'.$registration->getKey(),
            'type' => 'مسار تدريبي',
            'title' => $registration->learningPath?->title ?? '—',
            'context' => '—',
            'status' => $registration->status,
            'status_label' => RegistrationStatusDisplay::beneficiaryLabel($registration->status),
            'status_color' => RegistrationStatusDisplay::beneficiaryColor($registration->status),
            'created_at' => $registration->created_at,
            'approved_at' => $registration->approved_at,
            'completed_at' => $registration->completed_at,
            'sort_at' => $registration->created_at ?? now(),
            'url' => $registration->learningPath !== null
                ? LearningPathResource::getUrl('view', ['record' => $registration->learningPath])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function programRow(ProgramRegistration $registration): array
    {
        $program = $registration->trainingProgram;

        return [
            'id' => 'program-'.$registration->getKey(),
            'type' => 'برنامج تدريبي',
            'title' => $program?->title ?? '—',
            'context' => $program === null
                ? '—'
                : ($program->learning_path_id === null
                    ? 'برنامج مستقل'
                    : ($program->learningPath?->title ?? 'ضمن مسار')),
            'status' => $registration->status,
            'status_label' => RegistrationStatusDisplay::beneficiaryLabel($registration->status),
            'status_color' => RegistrationStatusDisplay::beneficiaryColor($registration->status),
            'created_at' => $registration->created_at,
            'approved_at' => $registration->approved_at,
            'completed_at' => $registration->isCompleted() ? $registration->updated_at : null,
            'sort_at' => $registration->created_at ?? now(),
            'url' => $program !== null
                ? TrainingProgramResource::getUrl('view', ['record' => $program])
                : null,
        ];
    }
}

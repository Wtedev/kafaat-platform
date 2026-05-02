<?php

namespace App\Services;

use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only ownership transfer for training programs and learning paths.
 *
 * TODO: When a centralized audit log exists, record owner_id changes (actor, old owner, new owner, entity).
 */
final class TrainingOwnershipTransferService
{
    public function transferProgramOwnership(TrainingProgram $program, int $newOwnerId, User $actor): void
    {
        Gate::forUser($actor)->authorize('transferOwnership', $program);

        $this->assertValidNewOwnerId($newOwnerId, $program->owner_id);

        $program->forceFill([
            'owner_id' => $newOwnerId,
            'updated_by' => $actor->getKey(),
        ])->save();
    }

    public function transferPathOwnership(LearningPath $path, int $newOwnerId, User $actor): void
    {
        Gate::forUser($actor)->authorize('transferOwnership', $path);

        $this->assertValidNewOwnerId($newOwnerId, $path->owner_id);

        $path->forceFill([
            'owner_id' => $newOwnerId,
            'updated_by' => $actor->getKey(),
        ])->save();
    }

    private function assertValidNewOwnerId(int $newOwnerId, ?int $currentOwnerId): void
    {
        if ($newOwnerId <= 0) {
            throw ValidationException::withMessages([
                'new_owner_id' => 'يجب اختيار مسؤول صالح.',
            ]);
        }

        if ($currentOwnerId !== null && (int) $currentOwnerId === $newOwnerId) {
            throw ValidationException::withMessages([
                'new_owner_id' => 'المسؤول المحدد هو بالفعل المسؤول الحالي.',
            ]);
        }

        $user = User::query()
            ->eligibleForTrainingOwnershipTransfer()
            ->whereKey($newOwnerId)
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'new_owner_id' => 'المستخدم غير موجود أو غير مؤهل ليكون مسؤولاً (موظف/إداري نشط).',
            ]);
        }
    }
}

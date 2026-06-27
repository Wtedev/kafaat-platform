<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Models\Profile;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use Illuminate\Support\Facades\Storage;

final class ProfileAnonymizationHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::ProfileAnonymization->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        $profile = Profile::query()->where('user_id', $context->target->id)->first();

        if ($profile === null) {
            return;
        }

        if (filled($profile->avatar)) {
            $disk = Storage::disk('public');

            if ($disk->exists($profile->avatar)) {
                $disk->delete($profile->avatar);
            }
        }

        $profile->forceFill([
            'gender' => null,
            'birth_date' => null,
            'city' => null,
            'job_title' => null,
            'bio' => null,
            'avatar' => null,
            'iconic_skill' => null,
            'iconic_skill_style' => null,
            'competency_levels' => null,
            'cv_sections' => null,
            'cv_sections_visibility' => null,
            'cv_path' => null,
            'current_cv_document_id' => null,
            'membership_badges' => null,
        ])->saveQuietly();
    }
}

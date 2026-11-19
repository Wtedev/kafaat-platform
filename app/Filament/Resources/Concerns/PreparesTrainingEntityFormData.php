<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\OpportunityStatus;
use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Models\VolunteerOpportunity;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

trait PreparesTrainingEntityFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stampTrainingEntityAuditFields(array $data): array
    {
        $userId = auth()->id();

        if ($userId === null) {
            return $data;
        }

        if (! filled($data['created_by'] ?? null)) {
            $data['created_by'] = $userId;
        }

        $data['updated_by'] = $userId;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function dropEmptyTrainingSlug(array $data): array
    {
        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        return $data;
    }

    protected function resolveProgramPublicationStatus(TrainingProgram $record, bool $visibleOnSite): ProgramStatus
    {
        return $this->resolvePublicationStatus($record, $visibleOnSite, ProgramStatus::class, 'publish');
    }

    protected function resolveOpportunityPublicationStatus(VolunteerOpportunity $record, bool $visibleOnSite): OpportunityStatus
    {
        return $this->resolvePublicationStatus($record, $visibleOnSite, OpportunityStatus::class, 'publish');
    }

    protected function canPublishNewTrainingProgram(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('publish', new TrainingProgram);
    }

    protected function canPublishNewVolunteerOpportunity(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('publish', new VolunteerOpportunity);
    }

    /**
     * @param  class-string<OpportunityStatus|ProgramStatus>  $statusEnum
     */
    private function resolvePublicationStatus(
        Model $record,
        bool $visibleOnSite,
        string $statusEnum,
        string $publishAbility,
    ): BackedEnum {
        /** @var OpportunityStatus|ProgramStatus $current */
        $current = $record->status;
        $wasArchived = $current === $statusEnum::Archived;
        $user = auth()->user();

        if ($wasArchived && ! $visibleOnSite) {
            return $statusEnum::Archived;
        }

        if ($visibleOnSite) {
            if ($current === $statusEnum::Published || ($user?->can($publishAbility, $record) ?? false)) {
                return $statusEnum::Published;
            }

            return $statusEnum::Draft;
        }

        if ($current === $statusEnum::Published && ! ($user?->can($publishAbility, $record) ?? false)) {
            return $statusEnum::Published;
        }

        return $statusEnum::Draft;
    }
}

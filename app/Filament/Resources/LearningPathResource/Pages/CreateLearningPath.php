<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Enums\PathStatus;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Support\TrainingEntityFormSupport;
use Filament\Schemas\Schema;

class CreateLearningPath extends BaseCreateRecord
{
    use PreparesTrainingEntityFormData;

    protected static string $resource = LearningPathResource::class;

    public function form(Schema $schema): Schema
    {
        return LearningPathResource::createForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);
        $data['status'] = $wantPublished && $this->canPublishNewLearningPath()
            ? PathStatus::Published->value
            : PathStatus::Draft->value;

        $data = TrainingEntityFormSupport::applyPublicationSchedule($data);

        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);
        $data = TrainingEntityFormSupport::stampOwnerFromCreator($data);

        return $this->dropEmptyTrainingSlug(
            $this->stampTrainingEntityAuditFields($data),
        );
    }
}

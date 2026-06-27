<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Enums\OpportunityStatus;
use App\Filament\Resources\Concerns\PreparesTrainingEntityFormData;
use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\VolunteerOpportunityResource;
use App\Filament\Support\TrainingEntityFormSupport;
use Filament\Schemas\Schema;

class CreateVolunteerOpportunity extends BaseCreateRecord
{
    use PreparesTrainingEntityFormData;

    protected static string $resource = VolunteerOpportunityResource::class;

    public function form(Schema $schema): Schema
    {
        return VolunteerOpportunityResource::createForm($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $wantPublished = TrainingEntityFormSupport::wantsPublishedStatus($data);
        $data['status'] = $wantPublished && $this->canPublishNewVolunteerOpportunity()
            ? OpportunityStatus::Published->value
            : OpportunityStatus::Draft->value;

        $data = TrainingEntityFormSupport::applyPublicationSchedule($data);
        $data = TrainingEntityFormSupport::applyCapacityUnlimited($data);

        return $this->dropEmptyTrainingSlug(
            $this->stampTrainingEntityAuditFields($data),
        );
    }
}

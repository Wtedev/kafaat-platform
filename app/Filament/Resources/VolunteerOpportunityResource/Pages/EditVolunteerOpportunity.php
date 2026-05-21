<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\VolunteerOpportunityResource;
use Filament\Actions\DeleteAction;

class EditVolunteerOpportunity extends BaseEditRecord
{
    protected static string $resource = VolunteerOpportunityResource::class;

    protected function getRecordToolbarActions(): array
    {
        return [DeleteAction::make()];
    }
}

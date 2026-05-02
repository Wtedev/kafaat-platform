<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\VolunteerOpportunityResource;
use Filament\Actions\CreateAction;

class ListVolunteerOpportunities extends BaseListRecords
{
    protected static string $resource = VolunteerOpportunityResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [CreateAction::make()];
    }
}

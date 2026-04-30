<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Filament\Resources\VolunteerOpportunityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVolunteerOpportunity extends ViewRecord
{
    protected static string $resource = VolunteerOpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}

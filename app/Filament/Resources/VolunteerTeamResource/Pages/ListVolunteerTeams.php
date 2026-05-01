<?php

namespace App\Filament\Resources\VolunteerTeamResource\Pages;

use App\Filament\Resources\VolunteerTeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerTeams extends ListRecords
{
    protected static string $resource = VolunteerTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

<?php

namespace App\Filament\Resources\VolunteerTeamResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\VolunteerTeamResource;
use Filament\Actions\CreateAction;

class ListVolunteerTeams extends BaseListRecords
{
    protected static string $resource = VolunteerTeamResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [CreateAction::make()];
    }
}

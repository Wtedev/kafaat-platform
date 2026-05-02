<?php

namespace App\Filament\Resources\VolunteerTeamResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\VolunteerTeamResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class ViewVolunteerTeam extends BaseViewRecord
{
    protected static string $resource = VolunteerTeamResource::class;

    protected function getViewPageToolbarActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}

<?php

namespace App\Filament\Resources\VolunteerTeamResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\VolunteerTeamResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditVolunteerTeam extends BaseEditRecord
{
    protected static string $resource = VolunteerTeamResource::class;

    protected function getRecordToolbarActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}

<?php

namespace App\Filament\Resources\VolunteerTeamResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\VolunteerTeamResource;
use Filament\Actions\EditAction;

class ViewVolunteerTeam extends BaseViewRecord
{
    protected static string $resource = VolunteerTeamResource::class;

    public function getTitle(): string
    {
        return 'الفريق التطوعي';
    }

    protected function getViewPageToolbarActions(): array
    {
        return [EditAction::make()];
    }
}

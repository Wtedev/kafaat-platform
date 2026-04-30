<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Filament\Resources\VolunteerOpportunityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerOpportunity extends EditRecord
{
    protected static string $resource = VolunteerOpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}

<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\VolunteerOpportunityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class ViewVolunteerOpportunity extends BaseViewRecord
{
    protected static string $resource = VolunteerOpportunityResource::class;

    protected function getViewPageToolbarActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}

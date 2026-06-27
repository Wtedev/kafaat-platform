<?php

namespace App\Filament\Resources\VolunteerOpportunityResource\Pages;

use App\Filament\Resources\Pages\RedirectsEditToViewSettingsTab;
use App\Filament\Resources\VolunteerOpportunityResource;

class EditVolunteerOpportunity extends RedirectsEditToViewSettingsTab
{
    protected static string $resource = VolunteerOpportunityResource::class;
}

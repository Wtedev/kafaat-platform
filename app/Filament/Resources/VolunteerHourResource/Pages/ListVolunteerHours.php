<?php

namespace App\Filament\Resources\VolunteerHourResource\Pages;

use App\Filament\Resources\VolunteerHourResource;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerHours extends ListRecords
{
    protected static string $resource = VolunteerHourResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

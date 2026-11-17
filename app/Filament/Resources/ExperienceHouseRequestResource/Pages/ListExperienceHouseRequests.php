<?php

namespace App\Filament\Resources\ExperienceHouseRequestResource\Pages;

use App\Filament\Resources\ExperienceHouseRequestResource;
use App\Filament\Resources\Pages\BaseListRecords;

class ListExperienceHouseRequests extends BaseListRecords
{
    protected static string $resource = ExperienceHouseRequestResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [];
    }
}

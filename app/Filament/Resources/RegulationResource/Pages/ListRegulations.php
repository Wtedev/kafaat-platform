<?php

namespace App\Filament\Resources\RegulationResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\RegulationResource;
use Filament\Actions\CreateAction;

class ListRegulations extends BaseListRecords
{
    protected static string $resource = RegulationResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()->label('إضافة لائحة'),
        ];
    }
}

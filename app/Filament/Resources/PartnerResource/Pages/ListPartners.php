<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\PartnerResource;
use Filament\Actions\CreateAction;

class ListPartners extends BaseListRecords
{
    protected static string $resource = PartnerResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة شريك'),
        ];
    }
}

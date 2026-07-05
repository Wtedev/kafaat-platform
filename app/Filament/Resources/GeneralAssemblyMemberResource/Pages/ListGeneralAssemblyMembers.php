<?php

namespace App\Filament\Resources\GeneralAssemblyMemberResource\Pages;

use App\Filament\Resources\GeneralAssemblyMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneralAssemblyMembers extends ListRecords
{
    protected static string $resource = GeneralAssemblyMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

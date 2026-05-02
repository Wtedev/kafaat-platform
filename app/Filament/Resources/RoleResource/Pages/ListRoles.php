<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\RoleResource;
use Filament\Actions\CreateAction;

class ListRoles extends BaseListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

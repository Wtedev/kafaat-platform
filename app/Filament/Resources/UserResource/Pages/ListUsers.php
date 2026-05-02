<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\UserResource;
use Filament\Actions\CreateAction;

class ListUsers extends BaseListRecords
{
    protected static string $resource = UserResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [CreateAction::make()];
    }
}

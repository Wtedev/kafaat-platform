<?php

namespace App\Filament\Resources\ProfileResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\ProfileResource;
use Filament\Actions\CreateAction;

class ListProfiles extends BaseListRecords
{
    protected static string $resource = ProfileResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view')),
        ];
    }
}

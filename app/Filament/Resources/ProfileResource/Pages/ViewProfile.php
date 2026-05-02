<?php

namespace App\Filament\Resources\ProfileResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\ProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class ViewProfile extends BaseViewRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getViewPageToolbarActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->getRecord()) ?? false),
        ];
    }
}

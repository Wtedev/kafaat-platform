<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class ViewUser extends BaseViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getViewPageToolbarActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->hidden(fn (): bool => $this->getRecord()->isProtectedAdminUser()),
        ];
    }
}

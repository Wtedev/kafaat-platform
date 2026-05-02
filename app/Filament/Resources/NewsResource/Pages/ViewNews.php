<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NewsResource\NewsPublicationFilamentActions;
use App\Filament\Resources\Pages\BaseViewRecord;
use App\Models\News;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class ViewNews extends BaseViewRecord
{
    protected static string $resource = NewsResource::class;

    protected function getViewPageToolbarActions(): array
    {
        /** @var Closure(): News $resolveNews */
        $resolveNews = fn (): News => $this->getRecord();

        return [
            EditAction::make(),
            DeleteAction::make(),
            ...NewsPublicationFilamentActions::viewPagePublicationGroup($resolveNews),
        ];
    }
}

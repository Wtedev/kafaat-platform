<?php

namespace App\Filament\Resources\NewsResource\Pages;

use App\Filament\Resources\NewsResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions\CreateAction;

class ListNews extends BaseListRecords
{
    protected static string $resource = NewsResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة خبر جديد')
                ->visible(fn (): bool => auth()->user()?->can('manage_news') ?? false),
        ];
    }
}

<?php

namespace App\Filament\Resources\MediaPhotoResource\Pages;

use App\Filament\Resources\MediaPhotoResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions\CreateAction;

class ListMediaPhotos extends BaseListRecords
{
    protected static string $resource = MediaPhotoResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()->label('إضافة صورة'),
        ];
    }
}

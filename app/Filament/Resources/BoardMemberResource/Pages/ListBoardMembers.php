<?php

namespace App\Filament\Resources\BoardMemberResource\Pages;

use App\Filament\Resources\BoardMemberResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions\CreateAction;

class ListBoardMembers extends BaseListRecords
{
    protected static string $resource = BoardMemberResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()->label('إضافة عضو'),
        ];
    }
}

<?php

namespace App\Filament\Resources\GovernanceDocumentResource\Pages;

use App\Filament\Resources\GovernanceDocumentResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions\CreateAction;

class ListGovernanceDocuments extends BaseListRecords
{
    protected static string $resource = GovernanceDocumentResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()->label('إضافة وثيقة'),
        ];
    }
}

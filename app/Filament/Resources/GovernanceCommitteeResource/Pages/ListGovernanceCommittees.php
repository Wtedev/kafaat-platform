<?php

namespace App\Filament\Resources\GovernanceCommitteeResource\Pages;

use App\Filament\Resources\GovernanceCommitteeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGovernanceCommittees extends ListRecords
{
    protected static string $resource = GovernanceCommitteeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\InvestmentDecisionYearResource\Pages;

use App\Filament\Resources\InvestmentDecisionYearResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentDecisionYears extends ListRecords
{
    protected static string $resource = InvestmentDecisionYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

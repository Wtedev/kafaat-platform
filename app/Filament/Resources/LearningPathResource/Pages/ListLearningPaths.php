<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions\CreateAction;

class ListLearningPaths extends BaseListRecords
{
    protected static string $resource = LearningPathResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [CreateAction::make()];
    }
}

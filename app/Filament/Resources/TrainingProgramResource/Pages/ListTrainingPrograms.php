<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\TrainingProgramResource;
use Filament\Actions\CreateAction;

class ListTrainingPrograms extends BaseListRecords
{
    protected static string $resource = TrainingProgramResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [CreateAction::make()];
    }
}

<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\TrainingProgramResource;
use Filament\Actions\Action;

class ListTrainingPrograms extends BaseListRecords
{
    protected static string $resource = TrainingProgramResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            Action::make('create')
                ->label('إضافة برنامج')
                ->url(TrainingProgramResource::getUrl('create'))
                ->icon('heroicon-o-plus')
                ->button()
                ->color('primary'),
        ];
    }
}

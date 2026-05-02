<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Enums\TrainingProgramKind;
use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\TrainingProgramResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class ListTrainingPrograms extends BaseListRecords
{
    protected static string $resource = TrainingProgramResource::class;

    protected function getListPageToolbarActions(): array
    {
        $base = TrainingProgramResource::getUrl('create');

        return [
            ActionGroup::make([
                Action::make('create_course')
                    ->label('دورة تدريبية')
                    ->url($base.'?kind='.TrainingProgramKind::Course->value),
                Action::make('create_workshop')
                    ->label('ورشة عمل')
                    ->url($base.'?kind='.TrainingProgramKind::Workshop->value),
                Action::make('create_session')
                    ->label('لقاء')
                    ->url($base.'?kind='.TrainingProgramKind::Session->value),
            ])
                ->label('إضافة برنامج')
                ->icon('heroicon-o-plus')
                ->button()
                ->color('primary'),
        ];
    }
}

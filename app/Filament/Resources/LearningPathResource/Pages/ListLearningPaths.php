<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Enums\LearningPathKind;
use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class ListLearningPaths extends BaseListRecords
{
    protected static string $resource = LearningPathResource::class;

    protected function getListPageToolbarActions(): array
    {
        $base = LearningPathResource::getUrl('create');

        return [
            ActionGroup::make([
                Action::make('create_training_path')
                    ->label('مسار تدريبي')
                    ->url($base.'?kind='.LearningPathKind::TrainingPath->value),
                Action::make('create_bootcamp')
                    ->label('معسكر')
                    ->url($base.'?kind='.LearningPathKind::Bootcamp->value),
            ])
                ->label('إضافة مسار')
                ->icon('heroicon-o-plus')
                ->button()
                ->color('primary'),
        ];
    }
}

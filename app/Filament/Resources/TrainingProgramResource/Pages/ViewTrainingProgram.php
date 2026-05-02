<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use App\Filament\Resources\TrainingProgramResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class ViewTrainingProgram extends BaseViewRecord
{
    protected static string $resource = TrainingProgramResource::class;

    protected function getViewPageToolbarActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}

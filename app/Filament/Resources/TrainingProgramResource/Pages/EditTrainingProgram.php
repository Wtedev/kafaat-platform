<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\TrainingProgramResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditTrainingProgram extends BaseEditRecord
{
    protected static string $resource = TrainingProgramResource::class;

    protected function getRecordToolbarActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}

<?php

namespace App\Filament\Resources\TrainingProgramResource\Pages;

use App\Filament\Resources\Pages\RedirectsEditToViewSettingsTab;
use App\Filament\Resources\TrainingProgramResource;

class EditTrainingProgram extends RedirectsEditToViewSettingsTab
{
    protected static string $resource = TrainingProgramResource::class;
}

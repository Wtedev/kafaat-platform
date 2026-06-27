<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\RedirectsEditToViewSettingsTab;

class EditLearningPath extends RedirectsEditToViewSettingsTab
{
    protected static string $resource = LearningPathResource::class;
}

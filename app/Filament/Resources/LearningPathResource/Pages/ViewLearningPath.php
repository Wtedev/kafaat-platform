<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Filament\Resources\LearningPathResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLearningPath extends ViewRecord
{
    protected static string $resource = LearningPathResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}

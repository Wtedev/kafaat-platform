<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

class ViewLearningPath extends BaseViewRecord
{
    protected static string $resource = LearningPathResource::class;

    protected function getViewPageToolbarActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}

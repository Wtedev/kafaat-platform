<?php

namespace App\Filament\Resources\LearningPathResource\Pages;

use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class EditLearningPath extends BaseEditRecord
{
    protected static string $resource = LearningPathResource::class;

    protected function getRecordToolbarActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}

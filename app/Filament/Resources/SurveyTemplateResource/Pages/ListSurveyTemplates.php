<?php

namespace App\Filament\Resources\SurveyTemplateResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\SurveyTemplateResource;
use Filament\Actions\CreateAction;

class ListSurveyTemplates extends BaseListRecords
{
    protected static string $resource = SurveyTemplateResource::class;

    protected function getListPageToolbarActions(): array
    {
        return [
            CreateAction::make()->label('إضافة قالب'),
        ];
    }
}

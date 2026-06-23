<?php

namespace App\Filament\Resources\VolunteerTeamResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\VolunteerTeamResource;

class EditVolunteerTeam extends BaseEditRecord
{
    protected static string $resource = VolunteerTeamResource::class;

    public function getTitle(): string
    {
        return 'تعديل الفريق التطوعي';
    }

    protected function getRecordToolbarActions(): array
    {
        return [];
    }
}

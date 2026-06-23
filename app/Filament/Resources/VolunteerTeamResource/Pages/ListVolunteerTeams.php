<?php

namespace App\Filament\Resources\VolunteerTeamResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\VolunteerTeamResource;
use App\Models\VolunteerTeam;

class ListVolunteerTeams extends BaseListRecords
{
    protected static string $resource = VolunteerTeamResource::class;

    public function mount(): void
    {
        $team = VolunteerTeam::canonical();
        if ($team !== null) {
            $this->redirect(VolunteerTeamResource::getUrl('view', ['record' => $team]), navigate: true);

            return;
        }

        parent::mount();
    }

    protected function getListPageToolbarActions(): array
    {
        return [];
    }
}

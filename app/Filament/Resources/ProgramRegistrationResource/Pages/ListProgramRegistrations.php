<?php

namespace App\Filament\Resources\ProgramRegistrationResource\Pages;

use App\Filament\Resources\ProgramRegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListProgramRegistrations extends ListRecords
{
    protected static string $resource = ProgramRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

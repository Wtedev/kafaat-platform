<?php

namespace App\Filament\Resources\PathRegistrationResource\Pages;

use App\Filament\Resources\PathRegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListPathRegistrations extends ListRecords
{
    protected static string $resource = PathRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

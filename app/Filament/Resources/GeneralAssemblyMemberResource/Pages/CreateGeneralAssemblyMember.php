<?php

namespace App\Filament\Resources\GeneralAssemblyMemberResource\Pages;

use App\Filament\Resources\GeneralAssemblyMemberResource;
use App\Models\BoardMember;
use Filament\Resources\Pages\CreateRecord;

class CreateGeneralAssemblyMember extends CreateRecord
{
    protected static string $resource = GeneralAssemblyMemberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['group'] = BoardMember::GROUP_GENERAL_ASSEMBLY;

        return $data;
    }
}

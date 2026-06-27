<?php

namespace App\Filament\Resources\CandidatePoolConsentVersionResource\Pages;

use App\Filament\Resources\CandidatePoolConsentVersionResource;
use App\Filament\Resources\Pages\BaseEditRecord;

class EditCandidatePoolConsentVersion extends BaseEditRecord
{
    protected static string $resource = CandidatePoolConsentVersionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}

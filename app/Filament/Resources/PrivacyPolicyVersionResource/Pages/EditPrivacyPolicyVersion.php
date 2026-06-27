<?php

namespace App\Filament\Resources\PrivacyPolicyVersionResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Resources\PrivacyPolicyVersionResource;

class EditPrivacyPolicyVersion extends BaseEditRecord
{
    protected static string $resource = PrivacyPolicyVersionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}

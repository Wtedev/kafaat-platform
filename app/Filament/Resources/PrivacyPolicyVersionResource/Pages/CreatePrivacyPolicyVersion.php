<?php

namespace App\Filament\Resources\PrivacyPolicyVersionResource\Pages;

use App\Filament\Resources\Pages\BaseCreateRecord;
use App\Filament\Resources\PrivacyPolicyVersionResource;
use App\Enums\PrivacyPolicyVersionStatus;

class CreatePrivacyPolicyVersion extends BaseCreateRecord
{
    protected static string $resource = PrivacyPolicyVersionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = PrivacyPolicyVersionStatus::Draft;
        $data['content_hash'] = '';
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}

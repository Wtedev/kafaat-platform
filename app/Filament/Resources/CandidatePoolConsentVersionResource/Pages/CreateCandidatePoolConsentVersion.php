<?php

namespace App\Filament\Resources\CandidatePoolConsentVersionResource\Pages;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Filament\Resources\CandidatePoolConsentVersionResource;
use App\Filament\Resources\Pages\BaseCreateRecord;

class CreateCandidatePoolConsentVersion extends BaseCreateRecord
{
    protected static string $resource = CandidatePoolConsentVersionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = PrivacyPolicyVersionStatus::Draft;
        $data['content_hash'] = '';
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}

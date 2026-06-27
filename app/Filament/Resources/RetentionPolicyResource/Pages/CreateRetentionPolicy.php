<?php

namespace App\Filament\Resources\RetentionPolicyResource\Pages;

use App\Filament\Resources\RetentionPolicyResource;
use App\Services\Privacy\Retention\RetentionPolicyManagementService;
use Filament\Resources\Pages\CreateRecord;

class CreateRetentionPolicy extends CreateRecord
{
    protected static string $resource = RetentionPolicyResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(RetentionPolicyManagementService::class)->createDraft($data, auth()->user());
    }
}

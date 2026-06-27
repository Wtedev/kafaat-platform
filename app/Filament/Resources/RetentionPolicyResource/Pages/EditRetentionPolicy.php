<?php

namespace App\Filament\Resources\RetentionPolicyResource\Pages;

use App\Filament\Resources\RetentionPolicyResource;
use App\Services\Privacy\Retention\RetentionPolicyManagementService;
use Filament\Resources\Pages\EditRecord;

class EditRetentionPolicy extends EditRecord
{
    protected static string $resource = RetentionPolicyResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(RetentionPolicyManagementService::class)->updateDraft($record, $data, auth()->user());
    }
}

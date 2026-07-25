<?php

namespace App\Filament\Resources\RetentionPolicyResource\Pages;

use App\Filament\Resources\RetentionPolicyResource;
use App\Services\Privacy\Retention\RetentionPolicyManagementService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRetentionPolicy extends EditRecord
{
    protected static string $resource = RetentionPolicyResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(RetentionPolicyManagementService::class)->updateDraft($record, $data, auth()->user());
    }
}

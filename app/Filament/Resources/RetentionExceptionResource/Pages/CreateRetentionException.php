<?php

namespace App\Filament\Resources\RetentionExceptionResource\Pages;

use App\Filament\Resources\RetentionExceptionResource;
use App\Services\Privacy\Retention\RetentionExceptionManagementService;
use Filament\Resources\Pages\CreateRecord;

class CreateRetentionException extends CreateRecord
{
    protected static string $resource = RetentionExceptionResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(RetentionExceptionManagementService::class)->create($data, auth()->user());
    }
}

<?php

namespace App\Filament\Resources\RetentionExceptionResource\Pages;

use App\Filament\Resources\RetentionExceptionResource;
use App\Services\Privacy\Retention\RetentionExceptionManagementService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRetentionException extends CreateRecord
{
    protected static string $resource = RetentionExceptionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(RetentionExceptionManagementService::class)->create($data, auth()->user());
    }
}

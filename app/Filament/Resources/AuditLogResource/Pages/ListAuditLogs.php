<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\Pages\BaseListRecords;

class ListAuditLogs extends BaseListRecords
{
    protected static string $resource = AuditLogResource::class;
}

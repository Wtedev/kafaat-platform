<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use App\Filament\Resources\RoleResource;

class ListRoles extends BaseListRecords
{
    protected static string $resource = RoleResource::class;

    public function getSubheading(): ?string
    {
        return 'عرض للمراجعة فقط — لتعيين الأدوار استخدم صفحة المستخدمين.';
    }

    protected function getListPageToolbarActions(): array
    {
        return [];
    }
}

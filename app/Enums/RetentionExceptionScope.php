<?php

namespace App\Enums;

enum RetentionExceptionScope: string
{
    case SingleResource = 'single_resource';
    case UserAllResources = 'user_all_resources';
    case ResourceTypeAll = 'resource_type_all';

    public function label(): string
    {
        return match ($this) {
            self::SingleResource => 'مورد واحد',
            self::UserAllResources => 'جميع موارد المستخدم',
            self::ResourceTypeAll => 'جميع موارد النوع',
        };
    }
}

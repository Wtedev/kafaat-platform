<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord as FilamentCreateRecord;

/**
 * يعطّل زر «إضافة وبدء إضافة المزيد» (create another) على كل صفحات الإنشاء.
 */
abstract class BaseCreateRecord extends FilamentCreateRecord
{
    protected static bool $canCreateAnother = false;
}

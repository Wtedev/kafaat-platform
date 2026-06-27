<?php

namespace App\Filament\Concerns;

use Filament\Tables\Table;

/**
 * يوجّه صفوف جدول البرامج/المسارات إلى صفحة العرض بدل التعديل.
 */
trait ConfiguresViewFirstTrainingResourceTable
{
    use ConfiguresViewFirstResourceTable;

    protected static function applyViewFirstTrainingTable(Table $table): Table
    {
        return static::applyViewFirstTable($table);
    }
}

<?php

namespace App\Filament\Concerns;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * يوجّه صفوف الجدول إلى صفحة العرض بدل التعديل.
 */
trait ConfiguresViewFirstResourceTable
{
    protected static function applyViewFirstTable(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): ?string => static::hasPage('view') && static::canView($record)
                ? static::getUrl('view', ['record' => $record])
                : null)
            ->recordAction(null);
    }
}

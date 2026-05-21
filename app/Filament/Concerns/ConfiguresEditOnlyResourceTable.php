<?php

namespace App\Filament\Concerns;

use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * يوجّه جداول الموارد إلى صفحة التعديل (أو صفحة التفاصيل عند عدم وجود تعديل).
 */
trait ConfiguresEditOnlyResourceTable
{
    protected static function applyEditOnlyTable(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Model $record): ?string => static::resolveEditOnlyRecordUrl($record))
            ->recordAction('edit');
    }

    protected static function makeTableEditAction(): EditAction
    {
        $action = EditAction::make();

        if (! static::hasPage('edit')) {
            $action->url(fn (Model $record): string => static::getUrl('view', ['record' => $record]));
        }

        return $action;
    }

    protected static function resolveEditOnlyRecordUrl(Model $record): ?string
    {
        if (static::hasPage('edit') && static::canEdit($record)) {
            return static::getUrl('edit', ['record' => $record]);
        }

        if (static::hasPage('view') && static::canView($record)) {
            return static::getUrl('view', ['record' => $record]);
        }

        return null;
    }
}

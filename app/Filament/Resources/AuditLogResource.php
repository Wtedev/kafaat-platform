<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?string $navigationLabel = 'سجل التدقيق';

    protected static ?string $modelLabel = 'حدث تدقيق';

    protected static ?string $pluralModelLabel = 'سجل التدقيق';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')->label('الوقت')->dateTime('j F Y — H:i')->sortable(),
                TextColumn::make('action')->label('الإجراء')->searchable(),
                TextColumn::make('actor.name')->label('المنفّذ')->placeholder('—'),
                TextColumn::make('targetUser.name')->label('المستهدف')->placeholder('—'),
                TextColumn::make('result')->label('النتيجة')->badge(),
                TextColumn::make('request_id')->label('Request ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')->label('النتيجة'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['actor', 'targetUser']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecurityLogResource\Pages\ListSecurityLogs;
use App\Models\SecurityLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SecurityLogResource extends Resource
{
    protected static ?string $model = SecurityLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?string $navigationLabel = 'سجل الأمان';

    protected static ?string $modelLabel = 'حدث أمني';

    protected static ?string $pluralModelLabel = 'سجل الأمان';

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
                TextColumn::make('event')->label('الحدث')->searchable(),
                TextColumn::make('result')->label('النتيجة')->badge(),
                TextColumn::make('severity')->label('الخطورة')->badge(),
                TextColumn::make('user.name')->label('المستخدم')->placeholder('—'),
                TextColumn::make('request_id')->label('Request ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityLogs::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RetentionRunResource\Pages;
use App\Models\RetentionRun;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RetentionRunResource extends Resource
{
    protected static ?string $model = RetentionRun::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'عمليات الاحتفاظ';

    protected static ?string $modelLabel = 'عملية احتفاظ';

    protected static ?string $pluralModelLabel = 'عمليات الاحتفاظ';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('retention_runs.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')->label('المرجع')->copyable(),
                TextColumn::make('policy.name')->label('السياسة')->placeholder('—'),
                TextColumn::make('resource_type')->label('المورد')->placeholder('—'),
                TextColumn::make('mode')->label('الوضع')->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state) => $state?->label()),
                TextColumn::make('cutoff_at')->label('Cutoff')->dateTime('Y-m-d H:i'),
                TextColumn::make('eligible_count')->label('مؤهل'),
                TextColumn::make('excluded_count')->label('مستبعد'),
                TextColumn::make('succeeded_count')->label('نجح'),
                TextColumn::make('failed_count')->label('فشل'),
                TextColumn::make('started_at')->label('بدء')->dateTime('Y-m-d H:i'),
                TextColumn::make('completed_at')->label('انتهاء')->dateTime('Y-m-d H:i')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRetentionRuns::route('/'),
        ];
    }
}

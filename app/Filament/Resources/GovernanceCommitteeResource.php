<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Resources\GovernanceCommitteeResource\Pages;
use App\Filament\Resources\GovernanceCommitteeResource\RelationManagers\MembersRelationManager;
use App\Models\GovernanceCommittee;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GovernanceCommitteeResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;

    protected static ?string $model = GovernanceCommittee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'اللجان الدائمة';

    protected static ?string $modelLabel = 'لجنة';

    protected static ?string $pluralModelLabel = 'اللجان الدائمة';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('manage_governance') || $user->hasRole(['super_admin', 'admin']));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات اللجنة')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('اسم اللجنة')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->minValue(0),

                    Toggle::make('is_active')
                        ->label('نشط')
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label('اسم اللجنة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('members_count')
                    ->label('عدد الأعضاء')
                    ->counts('members')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->actions([
                EditAction::make()->color('gray'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGovernanceCommittees::route('/'),
            'create' => Pages\CreateGovernanceCommittee::route('/create'),
            'edit' => Pages\EditGovernanceCommittee::route('/{record}/edit'),
        ];
    }
}

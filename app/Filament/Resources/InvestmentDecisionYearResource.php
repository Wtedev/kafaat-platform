<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Resources\InvestmentDecisionYearResource\Pages;
use App\Filament\Resources\InvestmentDecisionYearResource\RelationManagers\ItemsRelationManager;
use App\Models\InvestmentDecisionYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvestmentDecisionYearResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;

    protected static ?string $model = InvestmentDecisionYear::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'القرارات الاستثمارية';

    protected static ?string $modelLabel = 'سنة';

    protected static ?string $pluralModelLabel = 'القرارات الاستثمارية';

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
            Section::make('بيانات السنة')
                ->columns(2)
                ->schema([
                    TextInput::make('year')
                        ->label('السنة')
                        ->numeric()
                        ->required()
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->unique(ignoreRecord: true),

                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('قرارات الاستثمار لعام 2024')
                        ->columnSpanFull(),

                    TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->minValue(0)
                        ->helperText('الأرقام الأصغر تظهر أولاً'),

                    Toggle::make('is_active')
                        ->label('منشور')
                        ->default(true)
                        ->inline(false),

                    Textarea::make('empty_message')
                        ->label('رسالة عند عدم وجود قرارات')
                        ->rows(2)
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('تُعرض عندما لا توجد بنود قرارات لهذه السنة')
                        ->columnSpanFull(),

                    FileUpload::make('file_path')
                        ->label('مرفق PDF')
                        ->disk('public')
                        ->directory('governance/investment-decisions')
                        ->visibility('public')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(20480)
                        ->nullable()
                        ->helperText('ملف PDF — حتى 20 ميجابايت')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('year')
                    ->label('السنة')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('items_count')
                    ->label('عدد القرارات')
                    ->counts('items')
                    ->alignCenter(),

                IconColumn::make('file_path')
                    ->label('مرفق')
                    ->boolean()
                    ->getStateUsing(fn (InvestmentDecisionYear $record): bool => filled($record->file_path))
                    ->trueIcon('heroicon-o-document')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('is_active')
                    ->label('منشور')
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
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvestmentDecisionYears::route('/'),
            'create' => Pages\CreateInvestmentDecisionYear::route('/create'),
            'edit' => Pages\EditInvestmentDecisionYear::route('/{record}/edit'),
        ];
    }
}

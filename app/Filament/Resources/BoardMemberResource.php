<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Resources\BoardMemberResource\Pages;
use App\Models\BoardMember;
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
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BoardMemberResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;

    protected static ?string $model = BoardMember::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'الحوكمة';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'أعضاء مجلس الإدارة';

    protected static ?string $modelLabel = 'عضو';

    protected static ?string $pluralModelLabel = 'أعضاء مجلس الإدارة';

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
            Section::make('بيانات العضو')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('role')
                        ->label('المنصب / الدور')
                        ->maxLength(255)
                        ->nullable(),

                    TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->minValue(0),

                    Textarea::make('bio')
                        ->label('نبذة تعريفية')
                        ->rows(4)
                        ->maxLength(1000)
                        ->nullable()
                        ->columnSpanFull(),

                    FileUpload::make('photo')
                        ->label('الصورة الشخصية')
                        ->image()
                        ->disk('public')
                        ->directory('governance/board-members')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->nullable()
                        ->columnSpanFull(),

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
                ImageColumn::make('photo')
                    ->label('الصورة')
                    ->disk('public')
                    ->height(48)
                    ->circular(),

                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label('المنصب')
                    ->default('—'),

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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBoardMembers::route('/'),
            'create' => Pages\CreateBoardMember::route('/create'),
            'edit'   => Pages\EditBoardMember::route('/{record}/edit'),
        ];
    }
}

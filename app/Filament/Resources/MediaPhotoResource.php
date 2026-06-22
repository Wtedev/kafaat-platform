<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Resources\MediaPhotoResource\Pages;
use App\Models\MediaPhoto;
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

class MediaPhotoResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;

    protected static ?string $model = MediaPhoto::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'معرض الصور';

    protected static ?string $modelLabel = 'صورة';

    protected static ?string $pluralModelLabel = 'معرض الصور';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('manage_media') || $user->hasRole(['super_admin', 'admin']));
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
            Section::make('بيانات الصورة')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('album')
                        ->label('الألبوم / المجموعة')
                        ->maxLength(255)
                        ->nullable()
                        ->helperText('مثال: فعالية 2026، الحفل السنوي'),

                    TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->minValue(0),

                    Textarea::make('caption')
                        ->label('تعليق / وصف')
                        ->rows(2)
                        ->maxLength(500)
                        ->nullable()
                        ->columnSpanFull(),

                    FileUpload::make('image')
                        ->label('الصورة')
                        ->image()
                        ->disk('public')
                        ->directory('media/photos')
                        ->visibility('public')
                        ->maxSize(8192)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->required()
                        ->helperText('JPEG أو PNG أو WebP — حتى 8 ميجابايت')
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('منشور')
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
                ImageColumn::make('image')
                    ->label('الصورة')
                    ->disk('public')
                    ->height(64)
                    ->width(96),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('album')
                    ->label('الألبوم')
                    ->badge()
                    ->default('—'),

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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMediaPhotos::route('/'),
            'create' => Pages\CreateMediaPhoto::route('/create'),
            'edit'   => Pages\EditMediaPhoto::route('/{record}/edit'),
        ];
    }
}

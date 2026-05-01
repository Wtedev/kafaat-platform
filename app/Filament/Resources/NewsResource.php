<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsResource\Pages;
use App\Models\News;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الأخبار';

    protected static ?string $modelLabel = 'خبر';

    protected static ?string $pluralModelLabel = 'الأخبار';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل الخبر')->columns(2)->schema([
                TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->label('الرابط المختصر')
                    ->maxLength(255)
                    ->helperText('يُنشأ تلقائياً من العنوان إذا تُرك فارغاً')
                    ->columnSpanFull(),

                Textarea::make('excerpt')
                    ->label('المقتطف')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),

                Textarea::make('content')
                    ->label('المحتوى')
                    ->rows(12)
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('image')
                    ->label('رابط الصورة')
                    ->maxLength(500)
                    ->url()
                    ->nullable(),

                Select::make('category')
                    ->label('التصنيف')
                    ->options([
                        'إطلاق'    => 'إطلاق',
                        'ورشة عمل' => 'ورشة عمل',
                        'شراكة'    => 'شراكة',
                        'برامج'    => 'برامج',
                        'تقارير'   => 'تقارير',
                        'فعالية'   => 'فعالية',
                        'أخرى'     => 'أخرى',
                    ])
                    ->nullable(),

                DateTimePicker::make('published_at')
                    ->label('تاريخ النشر')
                    ->nullable()
                    ->helperText('اتركه فارغاً لحفظه كمسودة'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('category')
                    ->label('التصنيف')
                    ->badge()
                    ->default('—')
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label('تاريخ النشر')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->placeholder('مسودة'),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'view'   => Pages\ViewNews::route('/{record}'),
            'edit'   => Pages\EditNews::route('/{record}/edit'),
        ];
    }
}

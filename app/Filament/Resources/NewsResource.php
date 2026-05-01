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
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الأخبار';

    protected static ?string $modelLabel = 'خبر';

    protected static ?string $pluralModelLabel = 'الأخبار';

    /**
     * @return array<string, string>
     */
    protected static function categoryOptions(): array
    {
        return [
            'إطلاق' => 'إطلاق',
            'ورشة عمل' => 'ورشة عمل',
            'شراكة' => 'شراكة',
            'برامج' => 'برامج',
            'تقارير' => 'تقارير',
            'فعالية' => 'فعالية',
            'أخرى' => 'أخرى',
        ];
    }

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
                    ->options(static::categoryOptions())
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
                    ->url(fn (News $record): string => static::getUrl('edit', ['record' => $record]))
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->alignment(Alignment::Start),

                TextColumn::make('publication_status')
                    ->label('الحالة')
                    ->badge()
                    ->getStateUsing(function (News $record): string {
                        if ($record->published_at === null) {
                            return 'مسودة';
                        }

                        if ($record->published_at->isFuture()) {
                            return 'مجدول';
                        }

                        return 'منشور';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'منشور' => 'success',
                        'مجدول' => 'warning',
                        default => 'gray',
                    })
                    ->alignment(Alignment::Start),

                TextColumn::make('category')
                    ->label('التصنيف')
                    ->badge()
                    ->default('—')
                    ->sortable()
                    ->alignment(Alignment::Start),

                TextColumn::make('published_at')
                    ->label('نُشر في')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->alignment(Alignment::Start),

                TextColumn::make('created_at')
                    ->label('أُنشئ في')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignment(Alignment::Start),
            ])
            ->filters([
                SelectFilter::make('publication_status')
                    ->label('الحالة')
                    ->options([
                        'published' => 'منشور',
                        'scheduled' => 'مجدول',
                        'draft' => 'مسودة',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (blank($data['value'] ?? null)) {
                            return;
                        }

                        match ($data['value']) {
                            'published' => $query->published(),
                            'scheduled' => $query->scheduled(),
                            'draft' => $query->draft(),
                            default => null,
                        };
                    }),
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(static::categoryOptions()),
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
            'index' => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'view' => Pages\ViewNews::route('/{record}'),
            'edit' => Pages\EditNews::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Resources\RegulationResource\Pages;
use App\Models\Regulation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegulationResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;

    protected static ?string $model = Regulation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'اللوائح والأنظمة';

    protected static ?string $modelLabel = 'لائحة';

    protected static ?string $pluralModelLabel = 'اللوائح والأنظمة';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('manage_regulations') || $user->hasRole(['super_admin', 'admin']));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_regulations') || auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        return static::canCreate();
    }

    public static function canDelete($record): bool
    {
        return static::canCreate();
    }

    /**
     * @return array<string, string>
     */
    protected static function categoryOptions(): array
    {
        return [
            'لوائح تنظيمية'   => 'لوائح تنظيمية',
            'أنظمة داخلية'    => 'أنظمة داخلية',
            'سياسات'          => 'سياسات',
            'إجراءات'         => 'إجراءات',
            'أخرى'            => 'أخرى',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات اللائحة')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('العنوان')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('الوصف')
                        ->rows(3)
                        ->maxLength(1000)
                        ->nullable()
                        ->columnSpanFull(),

                    Select::make('category')
                        ->label('التصنيف')
                        ->options(static::categoryOptions())
                        ->nullable(),

                    TextInput::make('sort_order')
                        ->label('الترتيب')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->minValue(0),

                    FileUpload::make('file_path')
                        ->label('ملف اللائحة (PDF)')
                        ->disk('public')
                        ->directory('regulations/files')
                        ->visibility('public')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(20480)
                        ->nullable()
                        ->helperText('PDF — حتى 20 ميجابايت')
                        ->columnSpanFull(),

                    TextInput::make('file_url')
                        ->label('رابط خارجي للملف')
                        ->url()
                        ->maxLength(500)
                        ->nullable()
                        ->helperText('بديل عن رفع الملف — أدخل رابطاً خارجياً مباشراً')
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

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->date('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('الحالة')
                    ->options([
                        '1' => 'منشور',
                        '0' => 'غير منشور',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $v = $data['value'] ?? null;
                        if ($v === null || $v === '') {
                            return;
                        }
                        $query->where('is_active', $v === '1');
                    }),
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(static::categoryOptions()),
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
            'index'  => Pages\ListRegulations::route('/'),
            'create' => Pages\CreateRegulation::route('/create'),
            'edit'   => Pages\EditRegulation::route('/{record}/edit'),
        ];
    }
}

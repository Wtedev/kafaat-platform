<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Partner;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PartnerResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;
    use RegistersNavigationByPermission;

    protected static ?string $model = Partner::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'الشركاء';

    protected static ?string $modelLabel = 'شريك';

    protected static ?string $pluralModelLabel = 'الشركاء';

    protected static function requiredNavigationPermissions(): array
    {
        return ['manage_partners'];
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

    public static function canReorder(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الشريك')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('اسم الشريك')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    FileUpload::make('logo')
                        ->label('الشعار')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('partners')
                        ->visibility('public')
                        ->nullable()
                        ->columnSpanFull(),

                    TextInput::make('website_url')
                        ->label('رابط الموقع')
                        ->url()
                        ->maxLength(500)
                        ->nullable()
                        ->columnSpanFull(),

                    Toggle::make('is_active')
                        ->label('نشط')
                        ->default(true)
                        ->inline(false),

                    TextInput::make('sort_order')
                        ->label('ترتيب الظهور')
                        ->numeric()
                        ->default(0)
                        ->required()
                        ->minValue(0)
                        ->helperText('يُحدَّث تلقائياً عند السحب والإفلات من قائمة الشركاء.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
            ->description('اسحب الصفوف من أيقونة السحب ≡ لتغيير ترتيب ظهور الشركاء في الموقع. يُحفظ الترتيب تلقائياً.')
            ->reorderable('sort_order')
            ->reorderRecordsTriggerAction(
                fn (Action $action) => $action->hidden(),
            )
            ->defaultSort('sort_order', 'asc')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('ترتيب الظهور')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                ImageColumn::make('logo')
                    ->label('الشعار')
                    ->disk('public')
                    ->height(48)
                    ->square()
                    ->extraImgAttributes(['class' => 'object-contain']),

                TextColumn::make('name')
                    ->label('اسم الشريك')
                    ->searchable()
                    ->formatStateUsing(function (string $state, Partner $record): HtmlString {
                        $url = static::getUrl('edit', ['record' => $record]);

                        return new HtmlString(
                            '<a href="'.e($url).'" class="font-medium text-primary-600 hover:underline dark:text-primary-400">'.e($state).'</a>'
                        );
                    }),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->date('Y/m/d')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('الحالة')
                    ->options([
                        '1' => 'نشط',
                        '0' => 'غير نشط',
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $v = $data['value'] ?? null;
                        if ($v === null || $v === '') {
                            return;
                        }
                        $query->where('is_active', $v === '1' || $v === 1 || $v === true);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->color('gray'),
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
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'view' => Pages\ViewPartner::route('/{record}'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }
}

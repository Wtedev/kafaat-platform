<?php

namespace App\Filament\Resources;

use App\Enums\LearningPathKind;
use App\Enums\PathStatus;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\LearningPathResource\Pages;
use App\Filament\Resources\LearningPathResource\RelationManagers\LearningPathEditorsRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathCertificatesRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathRegistrationsRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\TrainingProgramsRelationManager;
use App\Models\LearningPath;
use App\Support\PublicDiskPath;
use App\Support\TrainingEntityAuthorization;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LearningPathResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = LearningPath::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?string $navigationLabel = 'المسارات';

    protected static ?string $modelLabel = 'مسار تعليمي';

    protected static ?string $pluralModelLabel = 'المسارات';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    protected static function requiredNavigationPermissions(): array
    {
        return ['paths.view'];
    }

    public static function resolveLearningPathImagePublicUrl(?string $path): string
    {
        return PublicDiskPath::urlOrPlaceholder($path, PublicDiskPath::PLACEHOLDER_TRAINING_CATALOG);
    }

    public static function defaultPathKindFromRequest(): string
    {
        $q = request()->query('kind') ?? request()->query('path_kind');
        $allowed = array_map(
            static fn (LearningPathKind $k): string => $k->value,
            LearningPathKind::cases(),
        );

        if (is_string($q) && in_array($q, $allowed, true)) {
            return $q;
        }

        return LearningPathKind::TrainingPath->value;
    }

    public static function learningPathImageUploadField(): FileUpload
    {
        return FileUpload::make('image')
            ->label('صورة المسار')
            ->image()
            ->disk('public')
            ->directory('learning-paths/images')
            ->visibility('public')
            ->maxSize(4096)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->imagePreviewHeight('14rem')
            ->imageResizeMode('cover')
            ->nullable()
            ->helperText('JPEG أو PNG أو WebP — حتى 4 ميجابايت. اختياري.')
            ->columnSpanFull();
    }

    /**
     * @return array<int, Component>
     */
    protected static function learningPathFormSections(bool $includeImageInBasicSection = true): array
    {
        $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

        $basicFields = [
            Hidden::make('path_kind')
                ->dehydrated()
                ->default(fn (): string => static::defaultPathKindFromRequest()),

            TextInput::make('title')
                ->label('اسم المسار')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('slug')
                ->label('الرابط المختصر')
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->visible($adminBypass)
                ->required($adminBypass)
                ->dehydrated($adminBypass)
                ->helperText('للمشرفين: يُستخدم في الروابط العامة.'),

            Textarea::make('description')
                ->label('نبذة')
                ->rows(4)
                ->columnSpanFull(),

            TextInput::make('capacity')
                ->label('السعة الاستيعابية للمسار')
                ->numeric()
                ->minValue(1)
                ->nullable()
                ->helperText('اتركه فارغاً لعدد غير محدود من المسجلين.'),
        ];

        if ($includeImageInBasicSection) {
            $basicFields[] = static::learningPathImageUploadField();
        }

        return [
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema($basicFields),

            Section::make('الظهور في الموقع')
                ->schema([
                    Toggle::make('visible_on_site')
                        ->label('ظاهر للزوار في الموقع')
                        ->helperText('فعّل لإظهار المسار، أو أطفئ لإخفائه عن الموقع العام.')
                        ->default(false)
                        ->onColor('success')
                        ->offColor('gray'),
                ]),

            Section::make('المسؤولية')
                ->visible(fn (?LearningPath $record): bool => $record !== null && $record->exists)
                ->schema([
                    TextEntry::make('owner_readonly')
                        ->label('المسؤول')
                        ->visible(fn (): bool => ! $adminBypass())
                        ->getStateUsing(function (LearningPath $record): string {
                            if ($record->owner_id !== null && $record->owner !== null) {
                                return $record->owner->name;
                            }

                            if ($record->created_by !== null && $record->creator !== null) {
                                return $record->creator->name;
                            }

                            return '—';
                        }),

                    Select::make('owner_id')
                        ->label('المسؤول (المالك)')
                        ->relationship('owner', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible($adminBypass)
                        ->dehydrated($adminBypass)
                        ->helperText('للمشرفين فقط: تعيين أو تغيير مالك المسار.'),
                ]),
        ];
    }

    public static function createForm(Schema $schema): Schema
    {
        return static::learningPathTwoColumnForm($schema, 'fi-learning-path-create-layout items-start gap-6 lg:gap-8');
    }

    /**
     * نموذج التعديل: نفس تخطيط الإنشاء (بدون تكرار حقل الصورة في العمود الأيمن).
     */
    public static function editForm(Schema $schema): Schema
    {
        return static::learningPathTwoColumnForm($schema, 'fi-learning-path-edit-layout items-start gap-6 lg:gap-8');
    }

    protected static function learningPathTwoColumnForm(Schema $schema, string $layoutClass): Schema
    {
        $sections = static::learningPathFormSections(includeImageInBasicSection: false);

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => $layoutClass.' fi-learning-path-two-col-ltr',
                ])
                ->schema([
                    Group::make([
                        Text::make('صورة المسار')
                            ->size(TextSize::ExtraSmall)
                            ->weight(FontWeight::SemiBold)
                            ->color('gray'),
                        static::learningPathImageUploadField(),
                    ])
                        ->columnSpan(1)
                        ->extraAttributes([
                            'class' => 'fi-learning-path-two-col-image rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
                        ]),
                    Group::make()
                        ->schema($sections)
                        ->columnSpan(1)
                        ->extraAttributes([
                            'class' => 'fi-learning-path-two-col-details flex min-w-0 flex-col gap-6',
                        ]),
                ]),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::learningPathFormSections(includeImageInBasicSection: true));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextEntry::make('title')
                            ->label('اسم المسار')
                            ->columnSpanFull(),

                        TextEntry::make('description')
                            ->label('نبذة عن المسار')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('path_publication_status')
                            ->label('الظهور في الموقع')
                            ->getStateUsing(fn (LearningPath $record): string => $record->status === PathStatus::Published
                                ? 'ظاهر'
                                : 'مخفي'),

                        TextEntry::make('programs_count')
                            ->label('عدد البرامج')
                            ->getStateUsing(fn (LearningPath $record): string => (string) ($record->programs_count ?? $record->programs()->count())),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'creator'])->withCount(['programs', 'registrations']))
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان المسار')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('responsible_display')
                    ->label('المسؤول')
                    ->getStateUsing(function (LearningPath $record): string {
                        if ($record->owner_id !== null && $record->owner !== null) {
                            return $record->owner->name;
                        }

                        if ($record->created_by !== null && $record->creator !== null) {
                            return $record->creator->name;
                        }

                        return '—';
                    }),

                TextColumn::make('programs_count')
                    ->label('عدد البرامج في المسار')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('registrations_count')
                    ->label('عدد المسجلين في المسار')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
            ])
            ->actions([
                ViewAction::make()
                    ->color('gray')
                    ->visible(fn (LearningPath $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()
                    ->color('gray')
                    ->visible(fn (LearningPath $record): bool => auth()->user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->color('danger')
                    ->visible(fn (LearningPath $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ])
            ->defaultSort('title');
    }

    public static function getRelations(): array
    {
        return [
            TrainingProgramsRelationManager::class,
            PathRegistrationsRelationManager::class,
            LearningPathEditorsRelationManager::class,
            PathCertificatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLearningPaths::route('/'),
            'create' => Pages\CreateLearningPath::route('/create'),
            'view' => Pages\ViewLearningPath::route('/{record}'),
            'edit' => Pages\EditLearningPath::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Enums\CompetencyTrack;
use App\Enums\LearningPathKind;
use App\Enums\PathStatus;
use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Concerns\ConfiguresViewFirstTrainingResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\Concerns\EntityNotesRelationManager;
use App\Filament\Resources\LearningPathResource\Pages;
use App\Filament\Resources\LearningPathResource\RelationManagers\LearningPathEditorsRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathAttendanceRegistrationsRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathGradesRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathRegistrationCertificatesRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\PathRegistrationsRelationManager;
use App\Filament\Resources\LearningPathResource\RelationManagers\TrainingProgramsRelationManager;
use App\Filament\Support\EntityTwoColumnFormLayout;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Models\LearningPath;
use App\Support\PublicDiskPath;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LearningPathResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;
    use ConfiguresViewFirstTrainingResourceTable;
    use RegistersNavigationByPermission;

    protected static ?string $model = LearningPath::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?string $navigationLabel = 'المسارات';

    protected static ?string $modelLabel = 'مسار تدريبي';

    protected static ?string $pluralModelLabel = 'المسارات التدريبية';

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
        return TrainingEntityFormSupport::coverImageUpload('learning-paths/images');
    }

    /**
     * @return array<int, Component>
     */
    protected static function learningPathCreateSections(): array
    {
        return [
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('اسم المسار')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('path_kind')
                        ->label('نوع المسار')
                        ->options(LearningPathKind::options())
                        ->default(fn (): string => static::defaultPathKindFromRequest())
                        ->required()
                        ->native(false)
                        ->columnSpanFull(),

                    Select::make('competency_track')
                        ->label('مسار الكفاءة')
                        ->options(CompetencyTrack::options())
                        ->nullable()
                        ->native(false)
                        ->columnSpanFull(),

                    TrainingEntityFormSupport::descriptionField(),
                ]),

            TrainingEntityFormSupport::publicationSection(PathStatus::Published),

            TrainingEntityFormSupport::advancedLearningPathSettingsSection(forEdit: false),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function learningPathEditSections(): array
    {
        return [
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('اسم المسار')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('path_kind')
                        ->label('نوع المسار')
                        ->options(LearningPathKind::options())
                        ->required()
                        ->native(false)
                        ->columnSpanFull(),

                    Select::make('competency_track')
                        ->label('مسار الكفاءة')
                        ->options(CompetencyTrack::options())
                        ->nullable()
                        ->native(false)
                        ->columnSpanFull(),

                    TrainingEntityFormSupport::descriptionField(),
                ]),

            TrainingEntityFormSupport::publicationSection(PathStatus::Published, forEdit: true),

            TrainingEntityFormSupport::advancedLearningPathSettingsSection(forEdit: true),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function learningPathFormSections(bool $includeImageInBasicSection = true): array
    {
        return static::learningPathEditSections();
    }

    public static function createForm(Schema $schema): Schema
    {
        return EntityTwoColumnFormLayout::wrap(
            $schema,
            static::learningPathImageUploadField(),
            static::learningPathCreateSections(),
            mode: 'create',
        );
    }

    public static function editForm(Schema $schema): Schema
    {
        return EntityTwoColumnFormLayout::wrap(
            $schema,
            static::learningPathImageUploadField(),
            static::learningPathEditSections(),
            mode: 'edit',
        );
    }

    public static function form(Schema $schema): Schema
    {
        return static::createForm($schema);
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
                            ->label('الوصف')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('path_publication_status')
                            ->label('حالة النشر')
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
        return static::applyViewFirstTrainingTable($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'creator'])->withCount(['programs', 'registrations']))
            ->columns([
                TextColumn::make('title')
                    ->label('اسم المسار')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('path_kind')
                    ->label('نوع المسار')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof LearningPathKind) {
                            return $state->label();
                        }

                        return LearningPathKind::tryFrom((string) $state)?->label() ?? '—';
                    }),

                TextColumn::make('responsible_display')
                    ->label('الموظف المسؤول')
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
            PathAttendanceRegistrationsRelationManager::class,
            PathGradesRelationManager::class,
            PathRegistrationCertificatesRelationManager::class,
            LearningPathEditorsRelationManager::class,
            EntityNotesRelationManager::class,
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

<?php

namespace App\Filament\Resources;

use App\Enums\ProgramStatus;
use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\TrainingProgramKind;
use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Concerns\ConfiguresViewFirstTrainingResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\LearningPathResource;
use App\Filament\Resources\LearningPathResource\RelationManagers\TrainingProgramsRelationManager;
use App\Filament\Resources\TrainingProgramResource\Pages;
use App\Filament\Resources\Concerns\EntityNotesRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramAttendanceRegistrationsRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramGradesRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationCertificatesRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationsRelationManager;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Filament\Support\EntityTwoColumnFormLayout;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Support\Format\LocaleFormat;
use App\Support\PublicDiskPath;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingProgramResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;
    use ConfiguresViewFirstTrainingResourceTable;
    use RegistersNavigationByPermission;

    protected static ?string $model = TrainingProgram::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|\UnitEnum|null $navigationGroup = 'التدريب';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'البرامج التدريبية';

    protected static ?string $modelLabel = 'برنامج تدريبي';

    protected static ?string $pluralModelLabel = 'البرامج التدريبية';

    protected static function requiredNavigationPermissions(): array
    {
        return ['programs.view'];
    }

    /**
     * مسار عام لمعاينة صورة البرنامج (تخزين محلي).
     */
    public static function resolveTrainingProgramImagePublicUrl(?string $path): string
    {
        return PublicDiskPath::urlOrPlaceholder($path, PublicDiskPath::PLACEHOLDER_TRAINING_CATALOG);
    }

    public static function trainingProgramImageUploadField(): FileUpload
    {
        return TrainingEntityFormSupport::coverImageUpload('training-programs/images');
    }

    public static function createForm(Schema $schema, ?int $presetLearningPathId = null): Schema
    {
        return EntityTwoColumnFormLayout::wrap(
            $schema,
            static::trainingProgramImageUploadField(),
            static::trainingProgramCreateSections($presetLearningPathId),
            mode: 'create',
        );
    }

    public static function createUrlForLearningPath(LearningPath|int $path): string
    {
        $pathId = $path instanceof LearningPath ? $path->getKey() : $path;

        return static::getUrl('create').'?'.http_build_query(['learning_path_id' => $pathId]);
    }

    public static function learningPathProgramsViewUrl(LearningPath|int $path): string
    {
        $pathId = $path instanceof LearningPath ? $path->getKey() : $path;

        return LearningPathResource::getUrl('view', [
            'record' => $pathId,
            'relation' => TrainingProgramsRelationManager::class,
        ]);
    }

    public static function editForm(Schema $schema): Schema
    {
        return EntityTwoColumnFormLayout::wrap(
            $schema,
            static::trainingProgramImageUploadField(),
            static::trainingProgramEditSections(),
            mode: 'edit',
        );
    }

    /**
     * @return array<int, Component>
     */
    protected static function trainingProgramCreateSections(?int $presetLearningPathId = null): array
    {
        $linkedToPath = $presetLearningPathId !== null;

        $basicFields = [];

        if ($linkedToPath) {
            $basicFields = [
                Hidden::make('is_linked_to_path')
                    ->default(true)
                    ->dehydrated(false)
                    ->live(),
                Hidden::make('learning_path_id')
                    ->default($presetLearningPathId),
            ];
        }

        return [
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema([
                    ...$basicFields,

                    TextInput::make('title')
                        ->label('اسم البرنامج')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('program_kind')
                        ->label('نوع البرنامج')
                        ->options(TrainingProgramKind::options())
                        ->default(fn (): string => static::defaultProgramKindFromRequest())
                        ->required()
                        ->native(false)
                        ->live()
                        ->columnSpan(1),

                    TrainingEntityFormSupport::competencyTrackSelect()
                        ->columnSpan(1),

                    ...TrainingEntityFormSupport::programDeliveryFields(),

                    ...TrainingEntityFormSupport::descriptionFieldsWithPreview(),
                ]),

            Section::make('مواعيد البرنامج')
                ->columns(2)
                ->schema([
                    ...TrainingEntityFormSupport::scheduleDateHiddenFields(hideRegistrationWhenLinked: true),
                    TrainingEntityFormSupport::trainingScheduleCalendar(
                        showRegistrationRange: fn (Get $get): bool => ! (bool) $get('is_linked_to_path'),
                        programHasEndDate: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                        showWeekdayPicker: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                    ),
                ]),

            TrainingEntityFormSupport::advancedProgramSettingsSection(
                forEdit: false,
                hidePathLinkFields: $linkedToPath,
            ),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function trainingProgramEditSections(): array
    {
        return [
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('اسم البرنامج')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('program_kind')
                        ->label('نوع البرنامج')
                        ->options(TrainingProgramKind::options())
                        ->required()
                        ->native(false)
                        ->live()
                        ->columnSpan(1),

                    TrainingEntityFormSupport::competencyTrackSelect()
                        ->columnSpan(1),

                    ...TrainingEntityFormSupport::programDeliveryFields(),

                    ...TrainingEntityFormSupport::descriptionFieldsWithPreview(),
                ]),

            Section::make('مواعيد البرنامج')
                ->columns(2)
                ->schema([
                    ...TrainingEntityFormSupport::scheduleDateHiddenFields(hideRegistrationWhenLinked: true),
                    TrainingEntityFormSupport::trainingScheduleCalendar(
                        showRegistrationRange: fn (Get $get): bool => ! (bool) $get('is_linked_to_path'),
                        programHasEndDate: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                        showWeekdayPicker: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                        showPublishSchedule: fn (?TrainingProgram $record): bool => TrainingEntityFormSupport::publishControlsVisibleForRecord(
                            $record,
                            ProgramStatus::Published,
                        ),
                    ),
                    Placeholder::make('registration_status_display')
                        ->label('حالة التسجيل الحالية')
                        ->content(fn (?TrainingProgram $record): string => ($record !== null && $record->exists)
                            ? $record->registrationWindowStatusLabel()
                            : '—')
                        ->visible(fn (?TrainingProgram $record): bool => $record !== null && $record->exists)
                        ->columnSpanFull(),
                ]),

            TrainingEntityFormSupport::advancedProgramSettingsSection(forEdit: true),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function trainingProgramFormSections(bool $includeImageInBasicSection = true): array
    {
        return static::trainingProgramEditSections();
    }

    public static function defaultProgramKindFromRequest(): string
    {
        $q = request()->query('kind');
        $allowed = array_map(
            static fn (TrainingProgramKind $k): string => $k->value,
            TrainingProgramKind::cases(),
        );

        if (is_string($q) && in_array($q, $allowed, true)) {
            return $q;
        }

        return TrainingProgramKind::Course->value;
    }

    public static function isSessionKindValue(string $kind): bool
    {
        return $kind === TrainingProgramKind::Session->value;
    }

    public static function programCoversMoreThanOneCalendarDay(mixed $start, mixed $end): bool
    {
        if (blank($start) || blank($end)) {
            return false;
        }

        $s = Carbon::parse((string) $start)->startOfDay();
        $e = Carbon::parse((string) $end)->startOfDay();

        return $e->gt($s);
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
                        TextEntry::make('path_follow')
                            ->label('')
                            ->visible(fn (TrainingProgram $record): bool => $record->learning_path_id !== null)
                            ->getStateUsing(function (TrainingProgram $record): string {
                                $title = $record->learningPath?->title ?? '—';

                                return 'تابع لمسار: '.$title;
                            })
                            ->columnSpanFull(),

                        TextEntry::make('title')
                            ->label('اسم البرنامج')
                            ->columnSpanFull(),

                        TextEntry::make('description')
                            ->label('الوصف')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('site_visibility_status')
                            ->label('حالة النشر')
                            ->getStateUsing(fn (TrainingProgram $record): string => $record->status === ProgramStatus::Published
                                ? 'ظاهر'
                                : 'مخفي'),

                        TextEntry::make('program_kind')
                            ->label('نوع البرنامج')
                            ->formatStateUsing(fn (TrainingProgram $record): string => $record->program_kind?->label() ?? '—'),

                        TextEntry::make('competency_track')
                            ->label('مسار الكفاءة')
                            ->formatStateUsing(fn (TrainingProgram $record): string => $record->competency_track?->shortLabel() ?? '—'),

                        TextEntry::make('delivery_mode')
                            ->label('طريقة التنفيذ')
                            ->formatStateUsing(fn (TrainingProgram $record): string => $record->deliveryModeDescription() ?? '—'),

                        TextEntry::make('registration_window_status')
                            ->label('حالة التسجيل')
                            ->getStateUsing(fn (TrainingProgram $record): string => $record->registrationWindowStatusLabel()),

                        TextEntry::make('program_duration')
                            ->label('مدة البرنامج')
                            ->getStateUsing(fn (TrainingProgram $record): string => $record->programDurationDescription()),

                        TextEntry::make('start_date')
                            ->label('تاريخ البدء')
                            ->formatStateUsing(function ($state): string {
                                if ($state === null) {
                                    return 'غير محدد';
                                }

                                $d = $state instanceof Carbon
                                    ? $state
                                    : \Illuminate\Support\Carbon::parse($state);

                                return LocaleFormat::fromCarbonFormat($d, 'j F Y');
                            }),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyViewFirstTrainingTable($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'creator', 'assignee', 'learningPath'])->withCount('registrations'))
            ->columns([
                ImageColumn::make('image')
                    ->label('الصورة')
                    ->disk('public')
                    ->defaultImageUrl(fn (TrainingProgram $record): string => $record->imagePublicUrl())
                    ->square()
                    ->imageSize(48)
                    ->toggleable(),

                TextColumn::make('title')
                    ->label('اسم البرنامج')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('path_label')
                    ->label('المسار')
                    ->getStateUsing(function (TrainingProgram $record): string {
                        if ($record->learning_path_id === null) {
                            return 'مستقل';
                        }

                        return $record->learningPath?->title ?? '—';
                    }),

                BadgeColumn::make('status')
                    ->label('حالة النشر')
                    ->colors([
                        'gray' => ProgramStatus::Draft->value,
                        'success' => ProgramStatus::Published->value,
                        'warning' => ProgramStatus::Archived->value,
                    ])
                    ->formatStateUsing(function ($state): ?string {
                        if ($state instanceof ProgramStatus) {
                            return $state->label();
                        }

                        return ProgramStatus::tryFrom((string) $state)?->label();
                    })
                    ->sortable(),

                TextColumn::make('program_kind')
                    ->label('نوع البرنامج')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof TrainingProgramKind) {
                            return $state->label();
                        }

                        return TrainingProgramKind::tryFrom((string) $state)?->label() ?? '—';
                    }),

                TextColumn::make('competency_track')
                    ->label('مسار الكفاءة')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof CompetencyTrack) {
                            return $state->shortLabel();
                        }

                        return CompetencyTrack::tryFrom((string) $state)?->shortLabel() ?? '—';
                    })
                    ->toggleable(),

                TextColumn::make('delivery_mode')
                    ->label('التنفيذ')
                    ->getStateUsing(fn (TrainingProgram $record): string => $record->deliveryModeDescription() ?? '—')
                    ->toggleable(),

                TextColumn::make('responsible_display')
                    ->label('الموظف المسؤول')
                    ->getStateUsing(function (TrainingProgram $record): string {
                        if ($record->owner_id !== null && $record->owner !== null) {
                            return $record->owner->name;
                        }

                        if ($record->created_by !== null && $record->creator !== null) {
                            return $record->creator->name;
                        }

                        if ($record->assigned_to !== null && $record->assignee !== null) {
                            return $record->assignee->name;
                        }

                        return '—';
                    }),

                TextColumn::make('registrations_count')
                    ->label('عدد المسجلين')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
            ])
            ->actions([
                DeleteAction::make()
                    ->color('danger')
                    ->visible(fn (TrainingProgram $record): bool => auth()->user()?->can('delete', $record) ?? false),
            ])
            ->defaultSort('title');
    }

    public static function getRelations(): array
    {
        return [
            ProgramRegistrationsRelationManager::class,
            ProgramAttendanceRegistrationsRelationManager::class,
            ProgramGradesRelationManager::class,
            ProgramRegistrationCertificatesRelationManager::class,
            EntityNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingPrograms::route('/'),
            'create' => Pages\CreateTrainingProgram::route('/create'),
            'view' => Pages\ViewTrainingProgram::route('/{record}'),
            'edit' => Pages\EditTrainingProgram::route('/{record}/edit'),
        ];
    }
}

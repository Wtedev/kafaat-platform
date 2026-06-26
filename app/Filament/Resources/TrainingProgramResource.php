<?php

namespace App\Filament\Resources;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\TrainingProgramResource\Pages;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramCertificatesRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationsRelationManager;
use App\Models\TrainingProgram;
use App\Filament\Support\TrainingEntityFormSupport;
use App\Support\PublicDiskPath;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingProgramResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;
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

    public static function createForm(Schema $schema): Schema
    {
        return static::trainingProgramTwoColumnForm($schema, 'fi-training-create-layout items-start gap-6 lg:gap-8', forEdit: false);
    }

    public static function editForm(Schema $schema): Schema
    {
        return static::trainingProgramTwoColumnForm($schema, 'fi-training-edit-layout items-start gap-6 lg:gap-8', forEdit: true);
    }

    protected static function trainingProgramTwoColumnForm(Schema $schema, string $layoutClass, bool $forEdit): Schema
    {
        $sections = $forEdit
            ? static::trainingProgramEditSections()
            : static::trainingProgramCreateSections();

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => $layoutClass.' fi-training-two-col-ltr',
                ])
                ->schema([
                    Group::make([
                        Text::make('صورة الغلاف')
                            ->size(TextSize::ExtraSmall)
                            ->weight(FontWeight::SemiBold)
                            ->color('gray'),
                        static::trainingProgramImageUploadField(),
                    ])
                        ->columnSpan(1)
                        ->extraAttributes([
                            'class' => 'fi-training-two-col-image rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900',
                        ]),
                    Group::make()
                        ->schema($sections)
                        ->columnSpan(1)
                        ->extraAttributes([
                            'class' => 'fi-training-two-col-details flex min-w-0 flex-col gap-6',
                        ]),
                ]),
        ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function trainingProgramCreateSections(): array
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
                        ->default(fn (): string => static::defaultProgramKindFromRequest())
                        ->required()
                        ->native(false)
                        ->live()
                        ->columnSpanFull(),

                    TrainingEntityFormSupport::descriptionField(),

                    ...TrainingEntityFormSupport::capacityFields(hideWhenLinkedToPath: true),
                ]),

            Section::make('المواعيد')
                ->columns(2)
                ->schema([
                    ...TrainingEntityFormSupport::scheduleDateHiddenFields(),
                    TrainingEntityFormSupport::trainingScheduleCalendar(
                        programHasEndDate: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                        showWeekdayPicker: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                    ),
                ]),

            TrainingEntityFormSupport::staffSectionForCreate(),
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
                    Toggle::make('is_linked_to_path')
                        ->label('تابع لمسار تدريبي')
                        ->helperText('عند التفعيل يُختار المسار؛ التسجيل يُدار من المسار.')
                        ->default(false)
                        ->live()
                        ->dehydrated(false),

                    Select::make('learning_path_id')
                        ->label('المسار التدريبي')
                        ->relationship(
                            name: 'learningPath',
                            titleAttribute: 'title',
                            modifyQueryUsing: fn (Builder $query) => $query->orderBy('title'),
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->required(fn (Get $get): bool => (bool) $get('is_linked_to_path'))
                        ->visible(fn (Get $get): bool => (bool) $get('is_linked_to_path'))
                        ->live(),

                    TextInput::make('path_sort_order')
                        ->label('ترتيب البرنامج داخل المسار')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->visible(fn (Get $get): bool => (bool) $get('is_linked_to_path') && filled($get('learning_path_id'))),

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
                        ->columnSpanFull(),

                    TrainingEntityFormSupport::descriptionField(),

                    ...TrainingEntityFormSupport::capacityFields(hideWhenLinkedToPath: true),
                ]),

            Section::make('المواعيد')
                ->columns(2)
                ->schema([
                    ...TrainingEntityFormSupport::scheduleDateHiddenFields(hideRegistrationWhenLinked: true),
                    TrainingEntityFormSupport::trainingScheduleCalendar(
                        showRegistrationRange: fn (Get $get): bool => ! (bool) $get('is_linked_to_path'),
                        programHasEndDate: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                        showWeekdayPicker: fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? '')),
                    ),
                    Placeholder::make('registration_status_display')
                        ->label('حالة التسجيل الحالية')
                        ->content(fn (?TrainingProgram $record): string => ($record !== null && $record->exists)
                            ? $record->registrationWindowStatusLabel()
                            : '—')
                        ->visible(fn (?TrainingProgram $record): bool => $record !== null && $record->exists)
                        ->columnSpanFull(),
                ]),

            TrainingEntityFormSupport::staffSectionForEdit(),

            Section::make('فريق التحرير')
                ->visible(fn (Get $get): bool => ! (bool) $get('is_linked_to_path'))
                ->schema([
                    Select::make('editors')
                        ->label('مشاركون في التحرير')
                        ->relationship(
                            name: 'editors',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->where('is_active', true)
                                ->orderBy('name'),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->dehydrated(fn (Get $get): bool => ! (bool) $get('is_linked_to_path'))
                        ->columnSpanFull(),
                ]),
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

                                return $d->translatedFormat('j F Y');
                            }),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'creator', 'assignee', 'learningPath'])->withCount('registrations'))
            ->columns([
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

                TextColumn::make('program_kind')
                    ->label('نوع البرنامج')
                    ->formatStateUsing(function ($state): string {
                        if ($state instanceof TrainingProgramKind) {
                            return $state->label();
                        }

                        return TrainingProgramKind::tryFrom((string) $state)?->label() ?? '—';
                    }),

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
                static::makeTableEditAction()
                    ->color('gray')
                    ->visible(fn (TrainingProgram $record): bool => (auth()->user()?->can('update', $record) || auth()->user()?->can('view', $record)) ?? false),
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
            ProgramCertificatesRelationManager::class,
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

<?php

namespace App\Filament\Resources;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\TrainingProgramResource\Pages;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramCertificatesRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationsRelationManager;
use App\Models\TrainingProgram;
use App\Support\FilamentAssignmentVisibility;
use App\Support\PublicDiskPath;
use App\Support\StaffFilamentRoles;
use App\Support\TrainingEntityAuthorization;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingProgramResource extends Resource
{
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
        return PublicDiskPath::urlOrPlaceholder($path);
    }

    public static function trainingProgramImageUploadField(): FileUpload
    {
        return FileUpload::make('image')
            ->label('صورة البرنامج')
            ->image()
            ->disk('public')
            ->directory('training-programs/images')
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
     * نموذج الإنشاء: صورة يسار، الحقول يمين (مثل الأخبار).
     */
    public static function createForm(Schema $schema): Schema
    {
        return static::trainingProgramTwoColumnForm($schema, 'fi-training-create-layout items-start gap-6 lg:gap-8');
    }

    /**
     * نموذج التعديل: نفس تخطيط الإنشاء (بدون تكرار حقل الصورة في العمود الأيمن).
     */
    public static function editForm(Schema $schema): Schema
    {
        return static::trainingProgramTwoColumnForm($schema, 'fi-training-edit-layout items-start gap-6 lg:gap-8');
    }

    protected static function trainingProgramTwoColumnForm(Schema $schema, string $layoutClass): Schema
    {
        $sections = static::trainingProgramFormSections(includeImageInBasicSection: false);

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 2])
                ->columnSpanFull()
                ->extraAttributes([
                    'class' => $layoutClass.' fi-training-two-col-ltr',
                ])
                ->schema([
                    Group::make([
                        Text::make('صورة البرنامج')
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
    protected static function trainingProgramFormSections(bool $includeImageInBasicSection = true): array
    {
        $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

        $assignedToVisible = function (?TrainingProgram $record): bool {
            if (! FilamentAssignmentVisibility::bypasses(auth()->user())) {
                return false;
            }

            return $record === null || ! $record->exists || $record->owner_id === null;
        };

        $basicSchema = [
            Hidden::make('program_kind')
                ->live()
                ->dehydrated()
                ->default(fn (): string => static::defaultProgramKindFromRequest()),

            Toggle::make('is_linked_to_path')
                ->label('تابع لمسار تدريبي')
                ->helperText('عند التفعيل يُختار المسار؛ تبويب التسجيل وأعضاء الفريق يُخفىان لأنها تُدار من المسار.')
                ->default(false)
                ->live()
                ->dehydrated(false),

            Select::make('learning_path_id')
                ->label('المسار التعليمي')
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
                ->visible(fn (Get $get): bool => (bool) $get('is_linked_to_path') && filled($get('learning_path_id')))
                ->helperText('ترتيب الظهور ضمن المسار.'),

            TextInput::make('title')
                ->label('اسم البرنامج')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->columnSpanFull(),

            Grid::make(2)->schema([
                DatePicker::make('start_date')
                    ->label('تاريخ بدء البرنامج')
                    ->live(),

                DatePicker::make('end_date')
                    ->label('تاريخ انتهاء البرنامج')
                    ->afterOrEqual('start_date')
                    ->live()
                    ->visible(fn (Get $get): bool => ! static::isSessionKindValue((string) ($get('program_kind') ?? ''))),
            ]),

            TextInput::make('slug')
                ->label('الرابط المختصر')
                ->maxLength(255)
                ->visible($adminBypass)
                ->required($adminBypass)
                ->dehydrated($adminBypass)
                ->helperText('للمشرفين: يُستخدم في الروابط العامة.'),

            Textarea::make('description')
                ->label('نبذة عن البرنامج')
                ->rows(4)
                ->columnSpanFull(),
        ];

        if ($includeImageInBasicSection) {
            $basicSchema[] = static::trainingProgramImageUploadField();
        }

        return [
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema($basicSchema),

            Section::make('الظهور في الموقع')
                ->schema([
                    Toggle::make('visible_on_site')
                        ->label('ظاهر للزوار في الموقع')
                        ->helperText('فعّل لإظهار البرنامج، أو أطفئ لإخفائه عن الموقع العام.')
                        ->default(false)
                        ->onColor('success')
                        ->offColor('gray'),
                ]),

            Section::make('التسجيل')
                ->description('يُخفى هذا القسم عند ربط البرنامج بمسار؛ يُدار التسجيل من إعدادات المسار.')
                ->visible(fn (Get $get): bool => ! (bool) $get('is_linked_to_path'))
                ->columns(2)
                ->schema([
                    Toggle::make('capacity_unlimited')
                        ->label('سعة غير محدودة')
                        ->default(true)
                        ->live()
                        ->dehydrated(false),

                    TextInput::make('capacity')
                        ->label('السعة الاستيعابية للبرنامج')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->visible(fn (Get $get): bool => ! (bool) $get('capacity_unlimited'))
                        ->dehydrated(fn (Get $get): bool => ! (bool) $get('is_linked_to_path') && ! (bool) $get('capacity_unlimited'))
                        ->helperText('عدد المقاعد عند وجود حد أقصى.'),

                    Grid::make(2)->schema([
                        DatePicker::make('registration_start')
                            ->label('تاريخ فتح التسجيل')
                            ->dehydrated(fn (Get $get): bool => ! (bool) $get('is_linked_to_path')),

                        DatePicker::make('registration_end')
                            ->label('تاريخ انتهاء مدة التسجيل')
                            ->afterOrEqual('registration_start')
                            ->dehydrated(fn (Get $get): bool => ! (bool) $get('is_linked_to_path')),
                    ])
                        ->columnSpanFull(),

                    TextEntry::make('registration_status_display')
                        ->label('حالة التسجيل (حالية)')
                        ->getStateUsing(fn (?TrainingProgram $record): string => ($record !== null && $record->exists)
                            ? $record->registrationWindowStatusLabel()
                            : '—')
                        ->visible(fn (?TrainingProgram $record): bool => $record !== null && $record->exists)
                        ->columnSpanFull(),
                ]),

            Section::make('التحضير')
                ->description('للبرامج التي تمتد أكثر من يوم تقويمي؛ يُخفى لنوع «لقاء».')
                ->visible(function (Get $get): bool {
                    if (static::isSessionKindValue((string) ($get('program_kind') ?? ''))) {
                        return false;
                    }

                    return static::programCoversMoreThanOneCalendarDay($get('start_date'), $get('end_date'));
                })
                ->schema([
                    CheckboxList::make('weekdays')
                        ->label('أيام التحضير الأسبوعية (لاحتساب التحضير)')
                        ->options([
                            '0' => 'الأحد',
                            '1' => 'الاثنين',
                            '2' => 'الثلاثاء',
                            '3' => 'الأربعاء',
                            '4' => 'الخميس',
                            '5' => 'الجمعة',
                            '6' => 'السبت',
                        ])
                        ->columns(4)
                        ->columnSpanFull()
                        ->helperText('يُستخدم عند امتداد البرنامج لأكثر من يوم واحد.'),
                ]),

            Section::make('أعضاء الفريق المسؤولين')
                ->description('محرّرون من الموظفين؛ يُخفى القسم عند ربط البرنامج بمسار.')
                ->visible(fn (Get $get): bool => ! (bool) $get('is_linked_to_path'))
                ->schema([
                    Select::make('editors')
                        ->label('إضافة من الموظفين')
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

            Section::make('المسؤولية')
                ->schema([
                    TextEntry::make('owner_display')
                        ->label('المسؤول')
                        ->visible(fn (): bool => ! $adminBypass())
                        ->getStateUsing(fn (?TrainingProgram $record): string => $record?->owner?->name ?? '—'),

                    Select::make('owner_id')
                        ->label('المسؤول (المالك)')
                        ->relationship('owner', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible($adminBypass)
                        ->dehydrated($adminBypass)
                        ->helperText('للمشرفين فقط: تعيين أو تغيير مالك البرنامج.'),

                    Select::make('assigned_to')
                        ->label('منسق تشغيلي')
                        ->relationship('assignee', 'name', modifyQueryUsing: fn (Builder $query) => $query->role(StaffFilamentRoles::assignableTrainingCoordinatorRoleNames()))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible($assignedToVisible)
                        ->required(fn (?TrainingProgram $record): bool => $assignedToVisible($record))
                        ->dehydrated(fn (?TrainingProgram $record): bool => $assignedToVisible($record))
                        ->helperText('يُعرض فقط عند عدم تعيين مالك للبرنامج؛ للمشرفين.'),
                ]),
        ];
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
        return $schema->components(static::trainingProgramFormSections(includeImageInBasicSection: true));
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
                            ->label('نبذة عن البرنامج')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('site_visibility_status')
                            ->label('الظهور في الموقع')
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
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'creator', 'assignee', 'learningPath'])->withCount('registrations'))
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان البرنامج')
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
                    ->label('المسؤول')
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
                ViewAction::make()
                    ->color('gray')
                    ->visible(fn (TrainingProgram $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()
                    ->color('gray')
                    ->visible(fn (TrainingProgram $record): bool => auth()->user()?->can('update', $record) ?? false),
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

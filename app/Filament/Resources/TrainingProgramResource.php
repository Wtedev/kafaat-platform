<?php

namespace App\Filament\Resources;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\TrainingProgramResource\Pages;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramCertificatesRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\ProgramRegistrationsRelationManager;
use App\Filament\Resources\TrainingProgramResource\RelationManagers\TrainingProgramEditorsRelationManager;
use App\Models\TrainingProgram;
use App\Support\FilamentAssignmentVisibility;
use App\Support\TrainingEntityAuthorization;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        if ($path === null || $path === '') {
            return asset('images/news-placeholder.svg');
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : asset('images/news-placeholder.svg');
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

        $basicFields = [
            TextInput::make('title')
                ->label('اسم البرنامج')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->columnSpanFull(),

            TextInput::make('slug')
                ->label('الرابط المختصر')
                ->maxLength(255)
                ->visible($adminBypass)
                ->required($adminBypass)
                ->dehydrated($adminBypass)
                ->helperText('للمشرفين: يُستخدم في الروابط العامة.'),

            Select::make('program_kind')
                ->label('نوع البرنامج')
                ->options(TrainingProgramKind::class)
                ->required()
                ->default(TrainingProgramKind::Course->value),

            Textarea::make('description')
                ->label('نبذة')
                ->rows(4)
                ->columnSpanFull(),
        ];

        if ($includeImageInBasicSection) {
            $basicFields[] = static::trainingProgramImageUploadField();
        }

        return [
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema($basicFields),

            Section::make('الظهور في الموقع')
                ->schema([
                    Toggle::make('visible_on_site')
                        ->label('ظاهر للزوار في الموقع')
                        ->helperText('فعّل لإظهار البرنامج، أو أطفئ لإخفائه عن الموقع العام.')
                        ->default(false)
                        ->onColor('success')
                        ->offColor('gray'),
                ]),

            Section::make('الجدولة والتسجيل')
                ->columns(2)
                ->schema([
                    TextInput::make('capacity')
                        ->label('السعة الاستيعابية')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('اتركه فارغاً لعدد غير محدود.'),

                    DatePicker::make('start_date')
                        ->label('تاريخ البدء')
                        ->live(),

                    DatePicker::make('end_date')
                        ->label('تاريخ الانتهاء')
                        ->afterOrEqual('start_date')
                        ->live(),

                    TextEntry::make('duration_hint')
                        ->label('المدة (محسوبة)')
                        ->state(function (Get $get): string {
                            $start = $get('start_date');
                            $end = $get('end_date');
                            if (blank($start) || blank($end)) {
                                return '—';
                            }
                            $s = Carbon::parse((string) $start)->startOfDay();
                            $e = Carbon::parse((string) $end)->startOfDay();
                            if ($e->lt($s)) {
                                return '—';
                            }
                            $days = max(1, (int) $s->diffInDays($e) + 1);

                            return sprintf('%d يوماً', $days);
                        })
                        ->columnSpanFull(),

                    Grid::make(2)->schema([
                        DatePicker::make('registration_start')
                            ->label('بداية التسجيل'),

                        DatePicker::make('registration_end')
                            ->label('نهاية التسجيل')
                            ->afterOrEqual('registration_start'),
                    ])
                        ->columnSpanFull(),

                    CheckboxList::make('weekdays')
                        ->label('أيام الجلسات الأسبوعية')
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
                        ->helperText('اختياري: لتوليد الجلسات والإشعارات حسب أيام الأسبوع.'),

                    TextEntry::make('registration_status_display')
                        ->label('حالة التسجيل (حالية)')
                        ->getStateUsing(fn (TrainingProgram $record): string => $record->exists
                            ? $record->registrationWindowStatusLabel()
                            : '—')
                        ->visible(fn (TrainingProgram $record): bool => $record->exists),
                ]),

            Section::make('الارتباط')
                ->schema([
                    TextEntry::make('learning_path_linked')
                        ->label('اسم المسار')
                        ->visible(fn (TrainingProgram $record): bool => $record->learning_path_id !== null)
                        ->getStateUsing(fn (TrainingProgram $record): string => $record->learningPath?->title ?? '—'),

                    Text::make('learning_path_unlinked')
                        ->content('لا يوجد ارتباط بمسار. الربط والترتيب من صفحة المسار التعليمي.')
                        ->visible(fn (TrainingProgram $record): bool => $record->learning_path_id === null),

                    TextInput::make('path_sort_order')
                        ->label('ترتيب البرنامج في المسار')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->visible(fn (?TrainingProgram $record): bool => $record !== null && $record->learning_path_id !== null)
                        ->helperText('يظهر عندما يكون البرنامج مرتبطاً بمسار.'),
                ]),

            Section::make('المسؤولية')
                ->schema([
                    TextEntry::make('owner_display')
                        ->label('المسؤول')
                        ->visible(fn (): bool => ! $adminBypass())
                        ->getStateUsing(fn (TrainingProgram $record): string => $record->owner?->name ?? '—'),

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
                        ->relationship('assignee', 'name', modifyQueryUsing: fn (Builder $query) => $query->role('training_manager'))
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
            TrainingProgramEditorsRelationManager::class,
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

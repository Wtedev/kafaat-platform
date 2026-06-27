<?php

namespace App\Filament\Support;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Filament\Forms\Components\TrainingScheduleCalendar;
use App\Models\TrainingProgram;
use App\Support\FilamentAssignmentVisibility;
use App\Support\StaffFilamentRoles;
use App\Support\TrainingEntityAuthorization;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class TrainingEntityFormSupport
{
  public static function coverImageUpload(
    string $directory,
    string $label = 'صورة الغلاف',
    ?string $helperText = null,
    string $previewHeight = '14rem',
  ): FileUpload {
    return FileUpload::make('image')
      ->label($label)
      ->image()
      ->disk('public')
      ->directory($directory)
      ->visibility('public')
      ->maxSize(4096)
      ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
      ->imagePreviewHeight($previewHeight)
      ->imageResizeMode('cover')
      ->nullable()
      ->helperText($helperText ?? 'JPEG أو PNG أو WebP — حتى 4 ميجابايت.')
      ->columnSpanFull();
  }

  public static function descriptionField(): Textarea
  {
    return Textarea::make('description')
      ->label('الوصف')
      ->rows(4)
      ->columnSpanFull();
  }

  /**
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function capacityFields(bool $hideWhenLinkedToPath = false): array
  {
    return [static::advancedSettingsSection($hideWhenLinkedToPath)];
  }

  public static function advancedSettingsSection(
    bool $hideWhenLinkedToPath = false,
    bool $includeProgramAudienceNotifications = false,
  ): Section {
    $capacityVisible = $hideWhenLinkedToPath
      ? fn (Get $get): bool => ! (bool) $get('is_linked_to_path')
      : null;

    $schema = static::registrationAdvancedSettingsFields(
      $capacityVisible,
      $includeProgramAudienceNotifications,
    );

    return Section::make('إعدادات متقدمة')
      ->columns(1)
      ->extraAttributes(['class' => 'fi-advanced-settings-section'])
      ->schema($schema);
  }

  public static function advancedProgramSettingsSection(bool $forEdit = false, bool $hidePathLinkFields = false): Section
  {
    $capacityVisible = fn (Get $get): bool => ! (bool) $get('is_linked_to_path');
    $staffVisible = $capacityVisible;

    $pathLinkFields = $hidePathLinkFields ? [] : static::programPathLinkFields();

    return static::collapsibleAdvancedSettingsSection([
      ...$pathLinkFields,
      ...static::registrationAdvancedSettingsFields(
        $capacityVisible,
        includeProgramAudienceNotifications: true,
      ),
      ...static::entityAdvancedStaffBlock(
        'مسؤولي البرنامج',
        static::programStaffSectionDescription(),
        $forEdit ? static::programStaffFieldsForEdit() : static::programStaffFieldsForCreate(),
        $staffVisible,
      ),
    ]);
  }

  /**
   * @return array{is_linked_to_path: bool, learning_path_id: int|null}
   */
  public static function programPathLinkFormState(TrainingProgram $program): array
  {
    return [
      'is_linked_to_path' => $program->learning_path_id !== null,
      'learning_path_id' => $program->learning_path_id,
    ];
  }

  /**
   * حالة نموذج الجدول الزمني للتعديل المضمّن (تواريخ بصيغة Y-m-d للتقويم).
   *
   * @return array<string, mixed>
   */
  public static function scheduleFormState(TrainingProgram $program): array
  {
    $formatDate = static fn (mixed $value): ?string => $value instanceof Carbon
      ? $value->toDateString()
      : (filled($value) ? (string) $value : null);

    $state = [
      'is_linked_to_path' => $program->learning_path_id !== null,
      'program_kind' => $program->program_kind?->value,
      'start_date' => $formatDate($program->start_date),
      'end_date' => $formatDate($program->end_date),
      'registration_start' => $formatDate($program->registration_start),
      'registration_end' => $formatDate($program->registration_end),
      'weekdays' => is_array($program->weekdays) ? $program->weekdays : [],
    ];

    return EntityPublicationFormData::mergePublicationUiState(
      $state,
      $program,
      ProgramStatus::Published,
    );
  }

  /**
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function programPathLinkFields(bool $persistToggleState = false): array
  {
    return [
      static::advancedSettingsToggle('is_linked_to_path', 'تابع لمسار تدريبي')
        ->default(false)
        ->live()
        ->dehydrated($persistToggleState)
        ->afterStateHydrated(function (Toggle $component, $state): void {
          if ($state) {
            return;
          }

          $record = $component->getRecord();

          if ($record instanceof TrainingProgram && $record->learning_path_id !== null) {
            $component->state(true);
          }
        })
        ->helperText('عند التفعيل يُختار المسار؛ التسجيل يُدار من المسار.'),

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
        ->live()
        ->columnSpanFull(),
    ];
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  public static function applyProgramPathLinkSettings(array $data): array
  {
    $linked = (bool) ($data['is_linked_to_path'] ?? false);
    unset($data['is_linked_to_path']);

    if (! $linked) {
      $data['learning_path_id'] = null;
      $data['path_sort_order'] = null;

      return $data;
    }

    $data['capacity'] = null;
    $data['registration_start'] = null;
    $data['registration_end'] = null;
    $data['weekdays'] = null;

    if (filled($data['learning_path_id'] ?? null) && blank($data['path_sort_order'] ?? null)) {
      $pathId = (int) $data['learning_path_id'];
      $max = (int) TrainingProgram::query()
        ->where('learning_path_id', $pathId)
        ->max('path_sort_order');
      $data['path_sort_order'] = $max > 0 ? $max + 1 : 1;
    }

    return $data;
  }

  public static function advancedLearningPathSettingsSection(bool $forEdit = false): Section
  {
    return static::collapsibleAdvancedSettingsSection([
      ...static::registrationAdvancedSettingsFields(null, includeProgramAudienceNotifications: false),
      static::advancedSettingsToggle('notify_on_publish', 'إشعارات المستفيدين')
        ->default(false),
      ...static::entityAdvancedStaffBlock(
        'المسؤولية',
        static::pathStaffSectionDescription(),
        $forEdit ? static::pathStaffFieldsForEdit() : static::pathStaffFieldsForCreate(),
      ),
    ]);
  }

  public static function advancedNewsSettingsSection(): Section
  {
    return static::collapsibleAdvancedSettingsSection([
      static::advancedSettingsToggle('notify_audience_on_publish', 'إشعارات المستفيدين')
        ->default(true)
        ->helperText('يُحترم عند النشر أو الجدولة. يمكن للمستفيدين إيقاف فئة الأخبار من إعداداتهم.'),
    ]);
  }

  public static function volunteerScheduleSection(): Section
  {
    return Section::make('مواعيد الفرصة')
      ->columns(2)
      ->schema([
        TextInput::make('hours_expected')
          ->label('الساعات المتوقعة')
          ->numeric()
          ->minValue(0)
          ->suffix('ساعة')
          ->columnSpanFull(),
        DatePicker::make('start_date')
          ->label('تاريخ البداية')
          ->native(false),
        DatePicker::make('end_date')
          ->label('تاريخ الانتهاء')
          ->native(false)
          ->afterOrEqual('start_date'),
      ]);
  }

  public static function advancedVolunteerSettingsSection(): Section
  {
    return static::collapsibleAdvancedSettingsSection([
      ...static::registrationAdvancedSettingsFields(
        null,
        includeProgramAudienceNotifications: false,
        includeAutoAcceptRegistrations: false,
      ),
      static::advancedSettingsToggle('notify_on_publish', 'إشعارات المستفيدين')
        ->default(true),
      static::advancedSettingsToggle('notify_registrants_on_update', 'تنبيه المسجّلين')
        ->default(false),
      ...static::entityAdvancedStaffBlock(
        'مسؤولية الفرصة',
        'يُحدَّد منسق التطوع المسؤول عن إدارة الفرصة في لوحة الإدارة.',
        static::volunteerStaffFields(),
      ),
    ]);
  }

  /**
   * @param  array<int, \Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>  $schema
   */
  public static function collapsibleAdvancedSettingsSection(array $schema): Section
  {
    return Section::make('إعدادات متقدمة')
      ->collapsible()
      ->collapsed()
      ->columns(1)
      ->extraAttributes(['class' => 'fi-advanced-settings-section'])
      ->schema($schema);
  }

  /**
   * @param  (Closure(Get): bool)|null  $capacityVisible
   * @return array<int, \Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
   */
  public static function registrationAdvancedSettingsFields(
    ?Closure $capacityVisible,
    bool $includeProgramAudienceNotifications,
    bool $includeAutoAcceptRegistrations = true,
  ): array {
    $capacityUnlimited = static::advancedSettingsToggle('capacity_unlimited', 'تسجيل غير محدود')
      ->default(true)
      ->live()
      ->dehydrated(false);

    $capacity = TextInput::make('capacity')
      ->label('الحد الأقصى للمسجّلين')
      ->numeric()
      ->minValue(1)
      ->nullable()
      ->suffix('مسجّل')
      ->maxWidth('xs')
      ->visible(fn (Get $get): bool => ! (bool) $get('capacity_unlimited'))
      ->dehydrated(fn (Get $get): bool => ! (bool) $get('capacity_unlimited'))
      ->extraAttributes(['class' => 'fi-advanced-settings__capacity']);

    $autoAccept = static::advancedSettingsToggle('auto_accept_registrations', 'قبول تلقائي')
      ->default(false);

    $capacityGroup = Group::make()
      ->columnSpanFull()
      ->schema([
        $capacityUnlimited,
        $capacity,
      ]);

    if ($capacityVisible !== null) {
      $capacityGroup->visible($capacityVisible);
      $capacityUnlimited->dehydrated($capacityVisible);
      if ($includeAutoAcceptRegistrations) {
        $autoAccept->visible($capacityVisible)->dehydrated($capacityVisible);
      }
    }

    $fields = [
      $capacityGroup,
    ];

    if ($includeAutoAcceptRegistrations) {
      $fields[] = $autoAccept;
    }

    if ($includeProgramAudienceNotifications) {
      $fields[] = static::advancedSettingsToggle('notify_audience', 'إشعارات المستفيدين')
        ->default(true);
    }

    return $fields;
  }

  public static function advancedSettingsToggle(string $name, string $label): Toggle
  {
    return Toggle::make($name)
      ->label($label)
      ->inline(true)
      ->extraFieldWrapperAttributes(['class' => 'fi-advanced-settings-toggle-row']);
  }

  /**
   * @param  array<int, \Filament\Forms\Components\Component>  $staffFields
   * @return array<int, Group>
   */
  private static function entityAdvancedStaffBlock(
    string $title,
    string $description,
    array $staffFields,
    ?Closure $visible = null,
  ): array {
    $group = Group::make()
      ->columnSpanFull()
      ->schema([
        Placeholder::make('advanced_staff_divider_'.md5($title))
          ->hiddenLabel()
          ->content(new HtmlString('<hr class="fi-advanced-settings__divider" />'))
          ->columnSpanFull(),
        Placeholder::make('advanced_staff_heading_'.md5($title))
          ->hiddenLabel()
          ->content(new HtmlString(
            '<div class="fi-advanced-settings__staff-heading">'
            .'<p class="fi-advanced-settings__staff-title">'.e($title).'</p>'
            .'<p class="fi-advanced-settings__staff-desc">'.e($description).'</p>'
            .'</div>'
          ))
          ->columnSpanFull(),
        ...$staffFields,
      ]);

    if ($visible !== null) {
      $group->visible($visible);
    }

    return [$group];
  }

  public static function pathStaffSectionDescription(): string
  {
    return 'يُعيَّن مالك المسار والموظف المنشئ لأغراض التتبع والصلاحيات.';
  }

  /**
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function pathStaffFieldsForCreate(): array
  {
    $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

    return [
      Placeholder::make('creator_display')
        ->label('الموظف المنشئ')
        ->content(fn (): string => auth()->user()?->name ?? '—')
        ->columnSpanFull(),
      Placeholder::make('owner_display_create')
        ->label('مالك المسار')
        ->content(fn (): string => auth()->user()?->name ?? '—')
        ->visible(fn (): bool => ! $adminBypass())
        ->columnSpanFull(),
      Select::make('owner_id')
        ->label('مالك المسار')
        ->relationship('owner', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
        ->searchable()
        ->preload()
        ->nullable()
        ->default(fn (): ?int => auth()->id())
        ->visible($adminBypass)
        ->dehydrated($adminBypass)
        ->helperText('يُعيَّن تلقائياً لمن ينشئ السجل ما لم يُحدَّد غيره.')
        ->columnSpanFull(),
    ];
  }

  /**
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function pathStaffFieldsForEdit(): array
  {
    $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

    return [
      Placeholder::make('creator_display_edit')
        ->label('الموظف المنشئ')
        ->content(function (?Model $record): string {
          if ($record === null) {
            return '—';
          }

          return $record->relationLoaded('creator')
            ? ($record->creator?->name ?? '—')
            : (string) ($record->creator()->value('name') ?? '—');
        })
        ->columnSpanFull(),
      Placeholder::make('owner_display_edit')
        ->label('مالك المسار')
        ->content(function (?Model $record): string {
          if ($record === null) {
            return '—';
          }

          if ($record->relationLoaded('owner') && $record->owner !== null) {
            return $record->owner->name;
          }

          if ($record->owner_id !== null) {
            return (string) ($record->owner()->value('name') ?? '—');
          }

          return $record->relationLoaded('creator')
            ? ($record->creator?->name ?? '—')
            : (string) ($record->creator()->value('name') ?? '—');
        })
        ->visible(fn (): bool => ! $adminBypass())
        ->columnSpanFull(),
      Select::make('owner_id')
        ->label('مالك المسار')
        ->relationship('owner', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
        ->searchable()
        ->preload()
        ->nullable()
        ->visible($adminBypass)
        ->dehydrated($adminBypass)
        ->columnSpanFull(),
    ];
  }

  /**
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function volunteerStaffFields(): array
  {
    $adminBypass = fn (): bool => FilamentAssignmentVisibility::bypasses(auth()->user());

    return [
      Select::make('assigned_to')
        ->label('منسق الفرصة')
        ->relationship(
          'assignee',
          'name',
          modifyQueryUsing: fn (Builder $query) => $query->role(StaffFilamentRoles::assignableVolunteeringCoordinatorRoleNames()),
        )
        ->searchable()
        ->preload()
        ->visible($adminBypass)
        ->required($adminBypass)
        ->dehydrated($adminBypass)
        ->helperText('يُحدَّد مسؤول التطوع عن إدارة الفرصة في لوحة الإدارة.')
        ->columnSpanFull(),
    ];
  }

  /**
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function programStaffFieldsForCreate(): array
  {
    $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

    return [
      Placeholder::make('owner_display_create')
        ->label('مالك البرنامج')
        ->content(fn (): string => auth()->user()?->name ?? '—')
        ->visible(fn (): bool => ! $adminBypass())
        ->columnSpanFull(),

      Select::make('owner_id')
        ->label('مالك البرنامج')
        ->relationship('owner', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
        ->searchable()
        ->preload()
        ->nullable()
        ->default(fn (): ?int => auth()->id())
        ->live()
        ->visible($adminBypass)
        ->dehydrated($adminBypass)
        ->afterStateUpdated(function (?int $state, Set $set, Get $get): void {
          if ($state === null) {
            return;
          }

          $editors = $get('editors') ?? [];
          $set('editors', static::normalizeProgramEditorIds(is_array($editors) ? $editors : [], $state));
        })
        ->columnSpanFull(),

      Select::make('editors')
        ->label('المسؤولون')
        ->relationship(
          name: 'editors',
          titleAttribute: 'name',
          modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
        )
        ->multiple()
        ->searchable()
        ->preload()
        ->default(fn (): array => array_filter([(int) auth()->id()]))
        ->helperText('اختر الموظفين المشاركين في إدارة البرنامج. مالك البرنامج يُضاف تلقائياً.')
        ->columnSpanFull(),
    ];
  }

  /**
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function programStaffFieldsForEdit(): array
  {
    $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

    return [
      Placeholder::make('owner_display_edit')
        ->label('مالك البرنامج')
        ->content(function (?Model $record): string {
          if ($record === null) {
            return '—';
          }

          if ($record->relationLoaded('owner') && $record->owner !== null) {
            return $record->owner->name;
          }

          if ($record->owner_id !== null) {
            return (string) ($record->owner()->value('name') ?? '—');
          }

          return $record->relationLoaded('creator')
            ? ($record->creator?->name ?? '—')
            : (string) ($record->creator()->value('name') ?? '—');
        })
        ->visible(fn (): bool => ! $adminBypass())
        ->columnSpanFull(),

      Select::make('owner_id')
        ->label('مالك البرنامج')
        ->relationship('owner', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
        ->searchable()
        ->preload()
        ->nullable()
        ->live()
        ->visible($adminBypass)
        ->dehydrated($adminBypass)
        ->afterStateUpdated(function (?int $state, Set $set, Get $get): void {
          if ($state === null) {
            return;
          }

          $editors = $get('editors') ?? [];
          $set('editors', static::normalizeProgramEditorIds(is_array($editors) ? $editors : [], $state));
        })
        ->columnSpanFull(),

      Select::make('editors')
        ->label('المسؤولون')
        ->relationship(
          name: 'editors',
          titleAttribute: 'name',
          modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
        )
        ->multiple()
        ->searchable()
        ->preload()
        ->helperText('اختر الموظفين المشاركين في إدارة البرنامج. مالك البرنامج يُضاف تلقائياً.')
        ->dehydrated(fn (Get $get): bool => ! (bool) $get('is_linked_to_path'))
        ->columnSpanFull(),
    ];
  }

  /**
   * حقول التواريخ المخفية — يتحكم بها التقويم المرئي.
   *
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function scheduleDateHiddenFields(bool $hideRegistrationWhenLinked = false): array
  {
    $registrationVisible = $hideRegistrationWhenLinked
      ? fn (Get $get): bool => ! (bool) $get('is_linked_to_path')
      : null;

    $programEndVisible = fn (Get $get): bool => ($get('program_kind') ?? '') !== TrainingProgramKind::Session->value;

    $registrationStart = Hidden::make('registration_start')->live();
    $registrationEnd = Hidden::make('registration_end')->live();
    $programStart = Hidden::make('start_date')->live();
    $programEnd = Hidden::make('end_date')->live();

    if ($registrationVisible !== null) {
      $registrationStart->visible($registrationVisible)->dehydrated($registrationVisible);
      $registrationEnd->visible($registrationVisible)->dehydrated($registrationVisible);
    }

    $programEnd->visible($programEndVisible)->dehydrated($programEndVisible);

    $weekdays = Hidden::make('weekdays')->live()->default([]);

    $publishImmediately = Hidden::make('publish_immediately')->default(true)->live();
    $publishedAt = Hidden::make('published_at')->live();

    return [
      $registrationStart,
      $registrationEnd,
      $programStart,
      $programEnd,
      $weekdays,
      $publishImmediately,
      $publishedAt,
    ];
  }

  public static function trainingScheduleCalendar(
    bool | Closure $showRegistrationRange = true,
    bool | Closure $programHasEndDate = true,
    bool | Closure $showWeekdayPicker = true,
    bool | Closure $showPublishSchedule = true,
  ): TrainingScheduleCalendar {
    return TrainingScheduleCalendar::make('schedule_calendar')
      ->showRegistrationRange($showRegistrationRange)
      ->programHasEndDate($programHasEndDate)
      ->showWeekdayPicker($showWeekdayPicker)
      ->showPublishSchedule($showPublishSchedule);
  }

  public static function publicationSection(\BackedEnum $publishedStatus, bool $forEdit = false): Section
  {
    $section = Section::make('النشر')
      ->columns(1)
      ->schema(static::publicationInlineFields());

    if ($forEdit) {
      $section->visible(fn (?Model $record): bool => static::publishControlsVisibleForRecord(
        $record,
        $publishedStatus,
      ));
    }

    return $section;
  }

  /**
   * حقول النشر للمسارات والفرص (بدون تقويم موحّد).
   *
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function publicationInlineFields(): array
  {
    return [
      static::advancedSettingsToggle('publish_immediately', 'نشر فوراً')
        ->default(true)
        ->live(),

      DatePicker::make('published_at')
        ->label('موعد النشر')
        ->native(false)
        ->nullable()
        ->visible(fn (Get $get): bool => ! (bool) ($get('publish_immediately') ?? true))
        ->required(fn (Get $get): bool => ! (bool) ($get('publish_immediately') ?? true))
        ->columnSpanFull(),
    ];
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  public static function applyPublicationSchedule(array $data, bool $preserveExistingPublishTime = false): array
  {
    $immediate = (bool) ($data['publish_immediately'] ?? true);
    unset($data['publish_immediately']);

    if ($immediate) {
      if (! $preserveExistingPublishTime || blank($data['published_at'] ?? null)) {
        $data['published_at'] = now();
      }

      return $data;
    }

    $raw = $data['published_at'] ?? null;
    if (blank($raw)) {
      $data['published_at'] = null;

      return $data;
    }

    $at = \Illuminate\Support\Carbon::parse($raw)->startOfDay()->timezone(config('app.timezone'));

    if ($at->isSameDay(Carbon::today(config('app.timezone')))) {
      $data['published_at'] = now();

      return $data;
    }

    $data['published_at'] = $at;

    return $data;
  }

  public static function wantsImmediatePublication(array $data): bool
  {
    return (bool) ($data['publish_immediately'] ?? true);
  }

  public static function wantsPublishedStatus(array $data): bool
  {
    if (static::wantsImmediatePublication($data)) {
      return true;
    }

    $raw = $data['published_at'] ?? null;
    if (blank($raw)) {
      return false;
    }

    return ! Carbon::parse($raw)->startOfDay()->timezone(config('app.timezone'))->isFuture();
  }

  /**
   * @return array<int, string>
   */
  public static function validateProgramScheduleDates(array $data, bool $showRegistration = true): array
  {
    $errors = [];
    $today = Carbon::today(config('app.timezone'));
    $programStart = static::parseScheduleDate($data['start_date'] ?? null);
    $programEnd = static::parseScheduleDate($data['end_date'] ?? null) ?? $programStart;
    $registrationStart = $showRegistration
      ? static::parseScheduleDate($data['registration_start'] ?? null)
      : null;
    $registrationEnd = $showRegistration
      ? static::parseScheduleDate($data['registration_end'] ?? ($data['registration_start'] ?? null))
      : null;

    if (! static::wantsImmediatePublication($data)) {
      $publishAt = static::parseScheduleDate($data['published_at'] ?? null);
      if ($publishAt !== null && $publishAt->lt($today)) {
        $errors[] = 'لا يمكن تحديد تاريخ النشر قبل اليوم.';
      }
    }

    if ($showRegistration && $programStart !== null && $registrationStart !== null && $programStart->lt($registrationStart)) {
      $errors[] = 'لا يمكن أن يبدأ البرنامج قبل تاريخ بدء التسجيل.';
    }

    if ($showRegistration && $programEnd !== null && $registrationEnd !== null && $registrationEnd->gt($programEnd)) {
      $errors[] = 'يجب أن ينتهي التسجيل في أو قبل تاريخ انتهاء البرنامج.';
    }

    return $errors;
  }

  /**
   * @param  array<string, mixed>  $data
   */
  public static function assertValidProgramScheduleOrFail(array $data, bool $showRegistration = true): void
  {
    $errors = static::validateProgramScheduleDates($data, $showRegistration);

    if ($errors === []) {
      return;
    }

    throw ValidationException::withMessages([
      'schedule_calendar' => $errors,
    ]);
  }

  public static function parseScheduleDate(mixed $value): ?Carbon
  {
    if (blank($value)) {
      return null;
    }

    return Carbon::parse($value)->startOfDay()->timezone(config('app.timezone'));
  }

  /**
   * @param  array<string, mixed>  $data
   */
  public static function resolveEffectivePublishDateForValidation(array $data): ?Carbon
  {
    if (static::wantsImmediatePublication($data)) {
      return Carbon::today(config('app.timezone'));
    }

    return static::parseScheduleDate($data['published_at'] ?? null);
  }

  public static function resolvePublishImmediatelyFromRecord(
    \BackedEnum $status,
    ?\Illuminate\Support\Carbon $publishedAt,
    \BackedEnum $publishedStatus,
  ): bool {
    unset($status, $publishedStatus);

    return $publishedAt === null || ! $publishedAt->isFuture();
  }

  public static function publishControlsVisibleForRecord(?Model $record, \BackedEnum $publishedStatus): bool
  {
    if ($record === null || ! $record->exists) {
      return true;
    }

    $status = $record->status;

    if ($status instanceof \BackedEnum) {
      return $status !== $publishedStatus;
    }

    return (string) $status !== $publishedStatus->value;
  }

  public static function programStaffSectionDescription(): string
  {
    return 'يمنح المسؤولون المحدّدون صلاحية استعراض معلومات البرنامج، التحضير، إدارة الحضور، واستخراج الشهادات. يُضاف مالك البرنامج تلقائياً إلى القائمة.';
  }

  /**
   * @param  array<int|string>  $editorIds
   * @return array<int>
   */
  public static function normalizeProgramEditorIds(array $editorIds, ?int $ownerId): array
  {
    $ids = collect($editorIds)
      ->map(static fn ($id): int => (int) $id)
      ->filter(static fn (int $id): bool => $id > 0)
      ->unique()
      ->values();

    if ($ownerId !== null && $ownerId > 0) {
      $ids = $ids->prepend($ownerId)->unique()->values();
    }

    return $ids->all();
  }

  public static function programStaffSectionForCreate(): Section
  {
    return Section::make('مسؤولي البرنامج')
      ->description(static::programStaffSectionDescription())
      ->icon('heroicon-o-user-group')
      ->schema(static::programStaffFieldsForCreate());
  }

  public static function programStaffSectionForEdit(): Section
  {
    return Section::make('مسؤولي البرنامج')
      ->description(static::programStaffSectionDescription())
      ->icon('heroicon-o-user-group')
      ->visible(fn (Get $get): bool => ! (bool) $get('is_linked_to_path'))
      ->schema(static::programStaffFieldsForEdit());
  }

  public static function staffSectionForCreate(): Section
  {
    return Section::make('المسؤولية')
      ->description(static::pathStaffSectionDescription())
      ->columns(1)
      ->schema(static::pathStaffFieldsForCreate());
  }

    public static function staffSectionForEdit(): Section
    {
        return Section::make('المسؤولية')
            ->description(static::pathStaffSectionDescription())
            ->columns(1)
            ->schema(static::pathStaffFieldsForEdit());
    }

  /**
   * @param  array<string, mixed>  $data
   * @param  array<string, mixed>  $raw
   * @param  array<int, string>|null  $keys
   * @return array<string, mixed>
   */
  public static function mergeNonDehydratedFormFlags(array $data, array $raw, ?array $keys = null): array
  {
    $keys ??= [
      'is_linked_to_path',
      'capacity_unlimited',
    ];

    foreach ($keys as $key) {
      if (array_key_exists($key, $raw)) {
        $data[$key] = $raw[$key];
      }
    }

    return $data;
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  public static function applyCapacityUnlimited(array $data): array
  {
    if ((bool) ($data['capacity_unlimited'] ?? false)) {
      $data['capacity'] = null;
    }

    unset($data['capacity_unlimited']);

    return $data;
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  public static function applyAudienceNotifications(array $data): array
  {
    $notify = (bool) ($data['notify_audience'] ?? true);
    unset($data['notify_audience']);

    $data['notify_on_publish'] = $notify;
    $data['notify_milestones'] = $notify;
    $data['notify_registrants_on_update'] = false;

    return $data;
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  public static function stampOwnerFromCreator(array $data): array
  {
    $userId = auth()->id();

    if ($userId !== null && blank($data['owner_id'] ?? null)) {
      $data['owner_id'] = $userId;
    }

    return $data;
  }
}

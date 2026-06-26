<?php

namespace App\Filament\Support;

use App\Enums\TrainingProgramKind;
use App\Filament\Forms\Components\TrainingScheduleCalendar;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class TrainingEntityFormSupport
{
  public static function coverImageUpload(string $directory): FileUpload
  {
    return FileUpload::make('image')
      ->label('صورة الغلاف')
      ->image()
      ->disk('public')
      ->directory($directory)
      ->visibility('public')
      ->maxSize(4096)
      ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
      ->imagePreviewHeight('14rem')
      ->imageResizeMode('cover')
      ->nullable()
      ->helperText('JPEG أو PNG أو WebP — حتى 4 ميجابايت.')
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
    $linkedVisible = $hideWhenLinkedToPath
      ? fn (Get $get): bool => ! (bool) $get('is_linked_to_path')
      : null;

    $unlimited = Toggle::make('capacity_unlimited')
      ->label('طاقة غير محدودة')
      ->default(true)
      ->live()
      ->dehydrated(false);

    $capacity = TextInput::make('capacity')
      ->label('الطاقة الاستيعابية')
      ->numeric()
      ->minValue(1)
      ->nullable()
      ->visible(fn (Get $get): bool => ! (bool) $get('capacity_unlimited'))
      ->dehydrated(fn (Get $get): bool => ! (bool) $get('capacity_unlimited'));

    if ($linkedVisible !== null) {
      $unlimited->visible($linkedVisible);
      $capacity->visible(fn (Get $get): bool => $linkedVisible($get) && ! (bool) $get('capacity_unlimited'));
    }

    return [$unlimited, $capacity];
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
    $notifyAudience = Hidden::make('notify_audience')->default(false)->live();

    return [
      $registrationStart,
      $registrationEnd,
      $programStart,
      $programEnd,
      $weekdays,
      $publishImmediately,
      $publishedAt,
      $notifyAudience,
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

  /**
   * حقول النشر للمسارات (بدون تقويم موحّد).
   *
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function publicationInlineFields(): array
  {
    return [
      Toggle::make('publish_immediately')
        ->label('نشر فوراً')
        ->default(true)
        ->live()
        ->columnSpanFull(),

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

  public static function staffSectionForCreate(): Section
  {
    $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

    return Section::make('المسؤولية')
      ->columns(2)
      ->schema([
        Placeholder::make('creator_display')
          ->label('الموظف المنشئ')
          ->content(fn (): string => auth()->user()?->name ?? '—')
          ->columnSpan(1),

        Placeholder::make('owner_display_create')
          ->label('الموظف المسؤول')
          ->content(fn (): string => auth()->user()?->name ?? '—')
          ->visible(fn (): bool => ! $adminBypass())
          ->columnSpan(1),

        Select::make('owner_id')
          ->label('الموظف المسؤول')
          ->relationship('owner', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
          ->searchable()
          ->preload()
          ->nullable()
          ->default(fn (): ?int => auth()->id())
          ->visible($adminBypass)
          ->dehydrated($adminBypass)
          ->helperText('يُعيَّن تلقائياً لمن ينشئ السجل ما لم يُحدَّد غيره.')
          ->columnSpan(1),
      ]);
  }

    public static function staffSectionForEdit(): Section
    {
        $adminBypass = fn (): bool => TrainingEntityAuthorization::adminBypass(auth()->user());

        return Section::make('المسؤولية')
            ->columns(2)
            ->schema([
                Placeholder::make('creator_display_edit')
                    ->label('الموظف المنشئ')
                    ->content(function (?Model $record): string {
                        if ($record === null) {
                            return '—';
                        }

                        return $record->relationLoaded('creator')
                            ? ($record->creator?->name ?? '—')
                            : ($record->creator()->value('name') ?? '—');
                    }),

                Placeholder::make('owner_display_edit')
                    ->label('الموظف المسؤول')
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
                    ->visible(fn (): bool => ! $adminBypass()),

                Select::make('owner_id')
                    ->label('الموظف المسؤول')
                    ->relationship('owner', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible($adminBypass)
                    ->dehydrated($adminBypass),
            ]);
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
    $notify = (bool) ($data['notify_audience'] ?? false);
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

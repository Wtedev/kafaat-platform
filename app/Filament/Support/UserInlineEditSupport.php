<?php

namespace App\Filament\Support;

use App\Enums\ProfileGender;
use App\Models\Profile;
use App\Models\User;
use App\Support\UserAccountRoleForm;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UserInlineEditSupport
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function fields(): array
    {
        $fields = [];

        if (self::canEditAccountSection()) {
            $fields['account'] = self::accountFields();
        }

        if (self::canEditProfileSection()) {
            $fields['profile'] = self::profileFields();
        }

        if (self::canEditCompetencySection()) {
            $fields['competency'] = self::competencyFields();
        }

        if (self::canEditBioSection()) {
            $fields['bio'] = self::bioFields();
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    public static function editableSectionKeys(): array
    {
        return array_keys(self::fields());
    }

    public static function canEditAccountSection(): bool
    {
        return auth()->user()?->can('users.update') ?? false;
    }

    public static function canEditProfileSection(): bool
    {
        $actor = auth()->user();

        return $actor !== null && ($actor->can('roles.view') || $actor->can('edit_profile_badges'));
    }

    public static function canEditCompetencySection(): bool
    {
        return auth()->user()?->can('roles.view') ?? false;
    }

    public static function canEditBioSection(): bool
    {
        return auth()->user()?->can('roles.view') ?? false;
    }

    public static function canEditSection(string $field): bool
    {
        return match ($field) {
            'account' => self::canEditAccountSection(),
            'profile' => self::canEditProfileSection(),
            'competency' => self::canEditCompetencySection(),
            'bio' => self::canEditBioSection(),
            default => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'account' => 'معلومات الحساب',
            'profile' => 'الملف الشخصي',
            'competency' => 'مستويات الكفاءات',
            'bio' => 'نبذة عن المستفيد',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profileFormState(?Profile $profile): array
    {
        if ($profile === null) {
            return [
                'gender' => null,
                'birth_date' => null,
                'city' => null,
                'job_title' => null,
                'cv_language' => 'ar',
                'membership_badges' => [],
                'iconic_skill' => null,
                'iconic_skill_style' => 'amber',
            ];
        }

        return [
            'gender' => $profile->gender,
            'birth_date' => $profile->birth_date?->format('Y-m-d'),
            'city' => $profile->city,
            'job_title' => $profile->job_title,
            'cv_language' => $profile->cv_language ?? 'ar',
            'membership_badges' => is_array($profile->membership_badges) ? $profile->membership_badges : [],
            'iconic_skill' => $profile->iconic_skill,
            'iconic_skill_style' => $profile->iconic_skill_style ?? 'amber',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function competencyFormState(?Profile $profile): array
    {
        $levels = is_array($profile?->competency_levels) ? $profile->competency_levels : [];

        return [
            'competency_levels' => [
                'english' => $levels['english'] ?? null,
                'office' => $levels['office'] ?? null,
                'courses' => $levels['courses'] ?? null,
                'continuous_learning' => $levels['continuous_learning'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function extractProfileAttributesForField(string $field, array $data, ?User $actor): array
    {
        $actor ??= auth()->user();

        return match ($field) {
            'profile' => self::extractProfileSectionAttributes($data, $actor),
            'competency' => self::extractCompetencySectionAttributes($data),
            'bio' => self::extractBioSectionAttributes($data, $actor),
            default => throw ValidationException::withMessages([
                'field' => 'لا يمكن تعديل هذا القسم.',
            ]),
        };
    }

    /**
     * @return array<int, mixed>
     */
    private static function accountFields(): array
    {
        $fields = [
            TextInput::make('name')
                ->label('الاسم الكامل')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->maxLength(255),
            TextInput::make('phone')
                ->label('رقم الجوال')
                ->tel()
                ->maxLength(20),
            TextInput::make('password')
                ->label('كلمة المرور')
                ->password()
                ->helperText('اتركها فارغة إن لم تُرد تغييرها')
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->maxLength(255),
            Toggle::make('is_active')
                ->label('نشط'),
            Toggle::make('notify_email')
                ->label('إشعارات البريد'),
        ];

        if (UserAccountRoleForm::canActorEditRoleSection(auth()->user())) {
            $fields[] = Select::make('platform_role')
                ->label('الدور في المنصة')
                ->options(fn (): array => UserAccountRoleForm::platformRoleOptionsForActor(auth()->user()))
                ->required()
                ->native(false)
                ->searchable()
                ->visible(fn (?User $record): bool => UserAccountRoleForm::canActorEditRoleSection(auth()->user(), $record));
        }

        return $fields;
    }

    /**
     * @return array<int, mixed>
     */
    private static function profileFields(): array
    {
        $actor = auth()->user();
        $canRolesView = $actor?->can('roles.view') ?? false;
        $canEditBadges = $actor?->can('edit_profile_badges') ?? false;
        $invalidMessage = 'القيمة المحددة غير صحيحة.';
        $fields = [];

        if ($canRolesView) {
            $fields = array_merge($fields, [
                Select::make('gender')
                    ->label('الجنس')
                    ->options(ProfileGender::options())
                    ->required()
                    ->native(false),
                DatePicker::make('birth_date')
                    ->label('تاريخ الميلاد')
                    ->native(false)
                    ->nullable()
                    ->maxDate(now()),
                TextInput::make('city')
                    ->label('المدينة')
                    ->maxLength(100)
                    ->nullable(),
                TextInput::make('job_title')
                    ->label('المسمى الوظيفي')
                    ->maxLength(255)
                    ->nullable(),
                Select::make('cv_language')
                    ->label('لغة السيرة')
                    ->options([
                        'ar' => 'العربية',
                        'en' => 'الإنجليزية',
                    ])
                    ->default('ar')
                    ->native(false)
                    ->required(),
            ]);
        }

        if ($canEditBadges) {
            $fields = array_merge($fields, [
                CheckboxList::make('membership_badges')
                    ->label('شارات العضوية')
                    ->options([
                        'trainee' => 'متدرب',
                        'volunteer' => 'متطوع',
                    ])
                    ->default([])
                    ->columns(2)
                    ->helperText('تظهر شارة «مستفيد» دائماً؛ يمكن إضافة متدرب و/أو متطوع.')
                    ->rules(['nullable', 'array'])
                    ->nestedRecursiveRules([
                        Rule::in(['trainee', 'volunteer']),
                    ])
                    ->validationMessages([
                        'membership_badges.*.in' => $invalidMessage,
                    ])
                    ->columnSpanFull(),
                TextInput::make('iconic_skill')
                    ->label('المهارة المميزة')
                    ->placeholder('مثال: قائد مبادر، صانع أثر')
                    ->maxLength(120)
                    ->nullable()
                    ->live(onBlur: true)
                    ->columnSpanFull(),
                Select::make('iconic_skill_style')
                    ->label('لون شارة المهارة')
                    ->options([
                        'amber' => 'ذهبي',
                        'emerald' => 'أخضر',
                        'sky' => 'أزرق',
                        'rose' => 'وردي',
                        'violet' => 'أزرق غامق',
                        'brand' => 'لون الهوية',
                    ])
                    ->default('amber')
                    ->native(false)
                    ->nullable()
                    ->visible(fn (Get $get): bool => filled(trim((string) ($get('iconic_skill') ?? ''))))
                    ->rules([
                        'nullable',
                        Rule::in(Profile::allowedIconicSkillStyles()),
                    ])
                    ->validationMessages([
                        'iconic_skill_style.in' => $invalidMessage,
                    ]),
            ]);
        }

        return $fields;
    }

    /**
     * @return array<int, mixed>
     */
    private static function competencyFields(): array
    {
        return [
            TextInput::make('competency_levels.english')
                ->label('مستوى الإنجليزية')
                ->maxLength(120)
                ->nullable(),
            TextInput::make('competency_levels.office')
                ->label('مستوى برامج الأوفيس')
                ->maxLength(120)
                ->nullable(),
            TextInput::make('competency_levels.courses')
                ->label('مستوى الدورات')
                ->maxLength(120)
                ->nullable(),
            TextInput::make('competency_levels.continuous_learning')
                ->label('التعلم المستمر')
                ->maxLength(120)
                ->nullable(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function bioFields(): array
    {
        return [
            Textarea::make('bio')
                ->label('نبذة تعريفية')
                ->rows(5)
                ->maxLength(1000)
                ->nullable()
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function extractProfileSectionAttributes(array $data, ?User $actor): array
    {
        $attributes = [];

        if ($actor?->can('roles.view')) {
            foreach (['gender', 'birth_date', 'city', 'job_title', 'cv_language'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }
        }

        if ($actor?->can('edit_profile_badges')) {
            foreach (['membership_badges', 'iconic_skill', 'iconic_skill_style'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }
        }

        if ($attributes === []) {
            throw ValidationException::withMessages([
                'field' => 'لا تملك صلاحية تعديل هذا القسم.',
            ]);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function extractCompetencySectionAttributes(array $data): array
    {
        $submitted = is_array($data['competency_levels'] ?? null) ? $data['competency_levels'] : [];

        return [
            'competency_levels' => [
                'english' => $submitted['english'] ?? null,
                'office' => $submitted['office'] ?? null,
                'courses' => $submitted['courses'] ?? null,
                'continuous_learning' => $submitted['continuous_learning'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function extractBioSectionAttributes(array $data, ?User $actor): array
    {
        if (! ($actor?->can('roles.view'))) {
            throw ValidationException::withMessages([
                'field' => 'لا تملك صلاحية تعديل هذا القسم.',
            ]);
        }

        return [
            'bio' => $data['bio'] ?? null,
        ];
    }
}

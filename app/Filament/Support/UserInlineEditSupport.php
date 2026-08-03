<?php

namespace App\Filament\Support;

use App\Enums\ProfileGender;
use App\Models\Profile;
use App\Models\User;
use App\Models\VolunteerTeam;
use App\Support\Auth\EmailNormalizer;
use App\Support\UserAccountRoleForm;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UserInlineEditSupport
{
    /**
     * @return array<int, mixed>
     */
    public static function fieldSchema(string $field): array
    {
        return match ($field) {
            'account' => self::canEditAccountSection() ? self::accountFields() : [],
            'profile' => self::canEditProfileSection() ? self::profileFields() : [],
            'competency' => self::canEditCompetencySection() ? self::competencyFields() : [],
            'bio' => self::canEditBioSection() ? self::bioFields() : [],
            default => [],
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::editableSectionKeys() as $field) {
            $fields[$field] = self::fieldSchema($field);
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    public static function editableSectionKeys(): array
    {
        $keys = [];

        foreach (array_keys(self::labels()) as $field) {
            if (self::canEditSection($field)) {
                $keys[] = $field;
            }
        }

        return $keys;
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
     * Allowlisted account modal keys persisted by {@see persistAccountSection()}.
     *
     * @return list<string>
     */
    public static function accountPersistAllowlist(): array
    {
        return [
            'name',
            'email',
            'phone',
            'password',
            'is_active',
            'notify_email',
            'platform_role',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function accountFormState(User $user): array
    {
        $state = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => null,
            'is_active' => (bool) $user->is_active,
            'notify_email' => (bool) $user->notify_email,
        ];

        if (UserAccountRoleForm::canActorEditRoleSection(auth()->user(), $user)) {
            $state['platform_role'] = UserAccountRoleForm::platformRoleFromUser($user);
        }

        return $state;
    }

    /**
     * Persist only allowlisted account fields. Does not touch profile / identity / registrations.
     *
     * Email policy: trim; store lowercase (login rate-limit keys already lowercase; Auth::attempt
     * remains exact-match so canonical lowercase storage avoids case-mismatch lockouts).
     * Uniqueness is case-insensitive against other users; self ignored.
     *
     * email_verified_at: cleared when the normalized email actually changes; otherwise preserved.
     * OTP gate uses session (`otp_verified`), not email_verified_at.
     *
     * @param  array<string, mixed>  $submitted
     */
    public static function persistAccountSection(User $target, array $submitted, User $actor): void
    {
        $allowlisted = array_intersect_key(
            $submitted,
            array_flip(self::accountPersistAllowlist()),
        );

        $name = trim((string) ($allowlisted['name'] ?? ''));
        $email = self::normalizeAccountEmail((string) ($allowlisted['email'] ?? ''));
        $phone = array_key_exists('phone', $allowlisted)
            ? (filled($allowlisted['phone'] ?? null) ? trim((string) $allowlisted['phone']) : null)
            : $target->phone;
        $password = is_string($allowlisted['password'] ?? null) ? (string) $allowlisted['password'] : '';
        $isActive = array_key_exists('is_active', $allowlisted)
            ? (bool) $allowlisted['is_active']
            : (bool) $target->is_active;
        $notifyEmail = array_key_exists('notify_email', $allowlisted)
            ? (bool) $allowlisted['notify_email']
            : (bool) $target->notify_email;

        $messages = [
            'name.required' => 'الاسم الكامل مطلوب.',
            'name.max' => 'الاسم الكامل طويل جداً.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.max' => 'البريد الإلكتروني طويل جداً.',
            'phone.max' => 'رقم الجوال طويل جداً.',
            'password.max' => 'كلمة المرور طويلة جداً.',
        ];

        validator(
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password !== '' ? $password : null,
                'is_active' => $isActive,
                'notify_email' => $notifyEmail,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    function (string $attribute, mixed $value, \Closure $fail) use ($target): void {
                        $normalized = self::normalizeAccountEmail((string) $value);
                        if ($normalized === '') {
                            return;
                        }

                        $duplicate = User::query()
                            ->whereEmailIgnoreCase($normalized)
                            ->whereKeyNot($target->getKey())
                            ->exists();

                        if ($duplicate) {
                            $fail('البريد الإلكتروني مستخدم بالفعل.');
                        }
                    },
                ],
                'phone' => ['nullable', 'string', 'max:20'],
                'password' => ['nullable', 'string', 'max:255'],
                'is_active' => ['boolean'],
                'notify_email' => ['boolean'],
            ],
            $messages,
        )->validate();

        $emailChanged = strcasecmp((string) $target->email, $email) !== 0;

        $canEditRole = UserAccountRoleForm::canActorEditRoleSection($actor, $target)
            && ! $target->isProtectedAdminUser();
        $requestedPlatformRole = array_key_exists('platform_role', $allowlisted)
            ? (string) ($allowlisted['platform_role'] ?? '')
            : null;

        $roleChanged = false;
        $resolvedRole = null;

        if ($canEditRole && $requestedPlatformRole !== null && $requestedPlatformRole !== '') {
            UserAccountRoleForm::assertActorMayAssign($actor, $requestedPlatformRole);
            $resolvedRole = UserAccountRoleForm::resolvePlatformRole($requestedPlatformRole);
            $currentPlatformRole = UserAccountRoleForm::platformRoleFromUser($target);
            $roleChanged = $resolvedRole['spatie'] !== $currentPlatformRole
                || (string) $target->role_type !== $resolvedRole['role_type'];
        }

        try {
            DB::transaction(function () use (
                $target,
                $name,
                $email,
                $phone,
                $password,
                $isActive,
                $notifyEmail,
                $emailChanged,
                $roleChanged,
                $resolvedRole,
            ): void {
                $attributes = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'is_active' => $isActive,
                    'notify_email' => $notifyEmail,
                ];

                if ($password !== '') {
                    // Plain password — User::$casts['password'] = 'hashed' hashes once.
                    $attributes['password'] = $password;
                }

                if ($roleChanged && $resolvedRole !== null) {
                    $attributes['role_type'] = $resolvedRole['role_type'];
                }

                $target->fill($attributes);

                if ($emailChanged) {
                    $target->email_verified_at = null;
                }

                $target->save();

                if ($roleChanged && $resolvedRole !== null) {
                    $target->syncRoles([$resolvedRole['spatie']]);
                    UserAccountRoleForm::applyRoleSideEffects($target, $resolvedRole['spatie']);

                    if ($resolvedRole['spatie'] === UserAccountRoleForm::TYPE_VOLUNTEER) {
                        VolunteerTeam::ensureMember($target);
                    }
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني مستخدم بالفعل.',
            ]);
        } catch (Throwable $exception) {
            Log::warning('filament.user_account_inline_edit_failed', [
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public static function normalizeAccountEmail(string $email): string
    {
        return EmailNormalizer::normalize($email);
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
                ->maxLength(255)
                ->validationMessages([
                    'required' => 'الاسم الكامل مطلوب.',
                    'max' => 'الاسم الكامل طويل جداً.',
                ]),
            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(fn ($state) => self::normalizeAccountEmail((string) ($state ?? '')))
                ->validationMessages([
                    'required' => 'البريد الإلكتروني مطلوب.',
                    'email' => 'صيغة البريد الإلكتروني غير صحيحة.',
                    'max' => 'البريد الإلكتروني طويل جداً.',
                ]),
            TextInput::make('phone')
                ->label('رقم الجوال')
                ->tel()
                ->maxLength(20)
                ->validationMessages([
                    'max' => 'رقم الجوال طويل جداً.',
                ]),
            TextInput::make('password')
                ->label('كلمة المرور')
                ->password()
                ->helperText('اتركها فارغة إن لم تُرد تغييرها')
                ->dehydrated(fn ($state) => filled($state))
                ->maxLength(255)
                ->validationMessages([
                    'max' => 'كلمة المرور طويلة جداً.',
                ]),
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

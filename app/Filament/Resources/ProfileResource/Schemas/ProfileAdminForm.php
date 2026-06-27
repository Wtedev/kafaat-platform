<?php

namespace App\Filament\Resources\ProfileResource\Schemas;

use App\Models\Profile;
use App\Support\UserAccountRoleForm;
use App\Support\Exports\BeneficiaryProfileExportColumns;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

final class ProfileAdminForm
{
    public static function configure(Schema $schema): Schema
    {
        $invalidMessage = 'القيمة المحددة غير صحيحة.';

        return $schema->components([
            Section::make('الحساب')
                ->description('بيانات تسجيل الدخول — للعرض فقط.')
                ->hiddenOn('create')
                ->columns(2)
                ->schema([
                    Placeholder::make('account_name')
                        ->label('الاسم الرباعي')
                        ->content(fn (?Profile $record): string => $record?->user?->fullName() ?? '—'),

                    Placeholder::make('account_identity')
                        ->label('رقم الهوية / الإقامة')
                        ->content(fn (?Profile $record): string => $record?->user?->maskedIdentityNumber() ?? '—')
                        ->visible(fn (?Profile $record): bool => $record?->user?->hasIdentityOnRecord() ?? false),

                    Placeholder::make('account_identity_type')
                        ->label('نوع الهوية')
                        ->content(fn (?Profile $record): string => $record?->user?->identity_type?->label() ?? '—')
                        ->visible(fn (?Profile $record): bool => $record?->user?->hasIdentityOnRecord() ?? false),

                    Placeholder::make('account_email')
                        ->label('البريد الإلكتروني')
                        ->content(fn (?Profile $record): string => $record?->user?->email ?? '—'),

                    Placeholder::make('account_phone')
                        ->label('رقم الجوال')
                        ->content(fn (?Profile $record): string => filled($record?->user?->phone) ? (string) $record->user->phone : '—'),

                    Placeholder::make('account_platform_role')
                        ->label('الدور في المنصة')
                        ->content(fn (?Profile $record): string => $record?->user
                            ? UserAccountRoleForm::tablePlatformRoleLabelAr($record->user)
                            : '—'),

                    Placeholder::make('account_is_active')
                        ->label('حالة الحساب')
                        ->content(fn (?Profile $record): string => (string) (BeneficiaryProfileExportColumns::resolve($record ?? new Profile, 'user_is_active') ?? '—')),
                ]),

            Section::make('البيانات الشخصية')
                ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view'))
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->label('ربط المستخدم')
                        ->columnSpanFull(),

                    Select::make('gender')
                        ->label('الجنس')
                        ->options([
                            'male' => 'ذكر',
                            'female' => 'أنثى',
                        ])
                        ->nullable(),

                    DatePicker::make('birth_date')
                        ->label('تاريخ الميلاد')
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

                    FileUpload::make('avatar')
                        ->label('الصورة الشخصية')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(2048)
                        ->disk('public')
                        ->directory('avatars')
                        ->visibility('public')
                        ->nullable(),

                    Textarea::make('bio')
                        ->label('نبذة تعريفية')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull()
                        ->nullable(),
                ]),

            Section::make('التميّز')
                ->description('شارات العضوية تُشتق من دور المستخدم في المنصة.')
                ->columns(2)
                ->schema([
                    Placeholder::make('membership_type_display')
                        ->label('نوع العضوية')
                        ->content(fn (?Profile $record): string => $record?->user
                            ? UserAccountRoleForm::tablePlatformRoleLabelAr($record->user)
                            : (string) (BeneficiaryProfileExportColumns::resolve($record ?? new Profile, 'membership_type') ?? '—')),

                    self::membershipBadgesFields($invalidMessage),

                    Placeholder::make('membership_badges_display')
                        ->label('شارات العضوية')
                        ->content(fn (?Profile $record): string => $record
                            ? implode(' · ', $record->displayMembershipBadges())
                            : '—')
                        ->columnSpanFull()
                        ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view') && ! auth()->user()?->can('edit_profile_badges')),

                    TextInput::make('iconic_skill')
                        ->label('المهارة المميزة')
                        ->placeholder('مثال: قائد مبادر، صانع أثر')
                        ->helperText('اترك الحقل فارغًا إذا لا توجد مهارة مميزة.')
                        ->maxLength(120)
                        ->nullable()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (mixed $state, callable $set, Get $get): void {
                            $t = trim((string) $state);
                            if ($t === '') {
                                $set('iconic_skill_style', null);

                                return;
                            }
                            if (! filled((string) ($get('iconic_skill_style') ?? ''))) {
                                $set('iconic_skill_style', 'amber');
                            }
                        })
                        ->columnSpanFull()
                        ->visible(fn (): bool => (bool) auth()->user()?->can('edit_profile_badges'))
                        ->disabled(fn (): bool => ! auth()->user()?->can('edit_profile_badges')),

                    Placeholder::make('iconic_skill_display')
                        ->label('المهارة المميزة')
                        ->content(fn (?Profile $record): string => $record?->iconicSkillLabel() ?? '—')
                        ->columnSpanFull()
                        ->visible(fn (): bool => ! auth()->user()?->can('edit_profile_badges')),

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
                        ->live()
                        ->visible(fn (Get $get): bool => (bool) auth()->user()?->can('edit_profile_badges') && filled(trim((string) ($get('iconic_skill') ?? ''))))
                        ->rules([
                            'nullable',
                            Rule::in(Profile::allowedIconicSkillStyles()),
                        ])
                        ->validationMessages([
                            'iconic_skill_style.in' => $invalidMessage,
                        ]),
                ]),

            Section::make('بطاقات الكفاءات')
                ->description('مستويات تعبئة المستفيد في البوابة.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view'))
                ->hiddenOn('create')
                ->columns(2)
                ->schema([
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
                ]),

            Section::make('بطاقات الكفاءات')
                ->visible(fn (): bool => (bool) auth()->user()?->can('edit_profile_badges') && ! auth()->user()?->can('roles.view'))
                ->hiddenOn('create')
                ->columns(2)
                ->schema([
                    self::readOnlyExportField('competency_english', 'مستوى الإنجليزية'),
                    self::readOnlyExportField('competency_office', 'مستوى برامج الأوفيس'),
                    self::readOnlyExportField('competency_courses', 'مستوى الدورات'),
                    self::readOnlyExportField('competency_continuous_learning', 'التعلم المستمر'),
                ]),

            Section::make('السيرة الذاتية — محتوى المنشئ')
                ->description('يُحرَّر تفصيلياً من بوابة المستفيد؛ هنا للعرض فقط.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view'))
                ->hiddenOn('create')
                ->columns(1)
                ->schema([
                    self::readOnlyCvField('cv_skills', 'المهارات'),
                    self::readOnlyCvField('cv_languages', 'اللغات'),
                    self::readOnlyCvField('cv_office_tools', 'أدوات المكتب'),
                    self::readOnlyCvField('cv_education', 'المؤهلات والتعليم'),
                    self::readOnlyCvField('cv_experience', 'الخبرات العملية'),
                    self::readOnlyCvField('cv_external_courses', 'الدورات الخارجية'),
                    self::readOnlyCvField('cv_links', 'الروابط والحسابات'),
                ]),

            Section::make('السيرة الذاتية — محتوى المنشئ')
                ->description('يُحرَّر من بوابة المستفيد.')
                ->visible(fn (): bool => (bool) auth()->user()?->can('edit_profile_badges') && ! auth()->user()?->can('roles.view'))
                ->hiddenOn('create')
                ->columns(1)
                ->schema([
                    self::readOnlyCvField('cv_skills', 'المهارات'),
                    self::readOnlyCvField('cv_languages', 'اللغات'),
                    self::readOnlyCvField('cv_office_tools', 'أدوات المكتب'),
                    self::readOnlyCvField('cv_education', 'المؤهلات والتعليم'),
                    self::readOnlyCvField('cv_experience', 'الخبرات العملية'),
                    self::readOnlyCvField('cv_external_courses', 'الدورات الخارجية'),
                    self::readOnlyCvField('cv_links', 'الروابط والحسابات'),
                    self::readOnlyExportField('cv_file_url', 'رابط ملف السيرة'),
                ]),

            Section::make('السيرة الذاتية — إعدادات')
                ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view'))
                ->hiddenOn('create')
                ->columns(2)
                ->schema([
                    Select::make('cv_language')
                        ->label('لغة واجهة السيرة')
                        ->options([
                            'ar' => 'العربية',
                            'en' => 'English',
                        ])
                        ->default('ar')
                        ->native(false)
                        ->required(),

                    FileUpload::make('cv_path')
                        ->label('ملف السيرة المرفوع')
                        ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(10240)
                        ->disk('public')
                        ->directory('cvs')
                        ->visibility('public')
                        ->nullable()
                        ->columnSpanFull(),

                    Placeholder::make('cv_file_link')
                        ->label('رابط الملف الحالي')
                        ->content(function (?Profile $record): HtmlString|string {
                            $url = $record?->cvPublicUrl();
                            if (! filled($url)) {
                                return '—';
                            }

                            return new HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener" class="text-primary-600 underline">فتح الملف</a>');
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function membershipBadgesFields(string $invalidMessage): CheckboxList
    {
        return CheckboxList::make('membership_badges')
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
            ->columnSpanFull()
            ->visible(fn (): bool => (bool) auth()->user()?->can('edit_profile_badges'))
            ->disabled(fn (): bool => ! auth()->user()?->can('edit_profile_badges'));
    }

    private static function readOnlyExportField(string $key, string $label): Placeholder
    {
        return Placeholder::make('export_display_'.$key)
            ->label($label)
            ->content(fn (?Profile $record): string => filled($record)
                ? (string) (BeneficiaryProfileExportColumns::resolve($record, $key) ?? '—')
                : '—');
    }

    private static function readOnlyCvField(string $key, string $label): Placeholder
    {
        return Placeholder::make('cv_display_'.$key)
            ->label($label)
            ->content(function (?Profile $record) use ($key): HtmlString|string {
                if ($record === null) {
                    return '—';
                }
                $text = BeneficiaryProfileExportColumns::resolve($record, $key);
                if (! filled($text)) {
                    return '—';
                }

                return new HtmlString('<div class="whitespace-pre-wrap text-sm leading-relaxed">'.e((string) $text).'</div>');
            })
            ->columnSpanFull();
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BelongsToStaffUiModule;
use App\Models\PendingEmailChange;
use App\Services\Auth\AccountPasswordChangeService;
use App\Services\Auth\EmailChangeService;
use App\Services\Staff\StaffProfileUpdateService;
use App\Support\Privacy\SensitiveContactMasker;
use App\Support\StaffUi\StaffUiModule;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 */
class StaffProfilePage extends Page
{
    use BelongsToStaffUiModule;

    protected static function staffUiModule(): string
    {
        return StaffUiModule::SHELL;
    }

    protected static ?string $slug = 'profile';

    protected static ?string $title = 'الملف الشخصي';

    protected static ?string $navigationLabel = 'الملف الشخصي';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 9999;

    protected static string|\UnitEnum|null $navigationGroup = null;

    public bool $emailOtpStep = false;

    public ?string $maskedPendingEmail = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->canAccessFilamentAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->syncEmailChangeUi();
        $this->fillProfileForm();
    }

    public function getTitle(): string|Htmlable
    {
        return 'الملف الشخصي';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        return $schema->components([
            Section::make('معلومات الحساب')
                ->description('الاسم ورقم الجوال والصورة. لا يُغيّر البريد أو كلمة المرور من زر «حفظ».')
                ->schema([
                    Placeholder::make('roles_display')
                        ->label('الدور')
                        ->content(fn (): string => $user->filamentStaffRoleLabelsAr())
                        ->columnSpanFull(),

                    TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('phone')
                        ->label('رقم الجوال')
                        ->tel()
                        ->maxLength(50)
                        ->nullable()
                        ->helperText('اختياري. الصيغ المقبولة: 05XXXXXXXX أو +9665XXXXXXXX'),

                    FileUpload::make('staff_photo')
                        ->label('الصورة الشخصية')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)
                        ->disk('public')
                        ->directory('staff-photos')
                        ->visibility('public')
                        ->nullable()
                        ->rules([
                            'nullable',
                            'image',
                            'mimes:jpeg,jpg,png,webp',
                            'max:5120',
                            'dimensions:max_width=4000,max_height=4000',
                        ])
                        ->validationMessages([
                            'image' => 'يجب أن يكون الملف صورة حقيقية (JPEG أو PNG أو WebP).',
                            'mimes' => 'الصيغ المسموحة فقط: JPEG و PNG و WebP. لا يُسمح بـ SVG أو GIF.',
                            'max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت.',
                            'dimensions' => 'أبعاد الصورة كبيرة جداً. الحد الأقصى 4000×4000 بكسل.',
                        ])
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('البريد الإلكتروني')
                ->description('تغيير البريد يتطلب إثبات ملكية العنوان الجديد برمز OTP.')
                ->schema([
                    Placeholder::make('current_email_display')
                        ->label('البريد الحالي')
                        ->content(fn (): string => (string) auth()->user()?->email)
                        ->columnSpanFull(),

                    Placeholder::make('pending_email_display')
                        ->label('البريد الجديد (قيد التحقق)')
                        ->content(fn (): string => (string) ($this->maskedPendingEmail ?? '—'))
                        ->visible(fn (): bool => $this->emailOtpStep)
                        ->columnSpanFull(),

                    TextInput::make('new_email')
                        ->label('البريد الإلكتروني الجديد')
                        ->email()
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->visible(fn (): bool => ! $this->emailOtpStep)
                        ->columnSpanFull(),

                    TextInput::make('new_email_confirmation')
                        ->label('تأكيد البريد الإلكتروني')
                        ->email()
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->visible(fn (): bool => ! $this->emailOtpStep)
                        ->columnSpanFull(),

                    TextInput::make('email_otp')
                        ->label('رمز التحقق')
                        ->maxLength(6)
                        ->dehydrated(false)
                        ->visible(fn (): bool => $this->emailOtpStep)
                        ->columnSpanFull(),

                    Actions::make([
                        Action::make('requestEmailChange')
                            ->label('إرسال رمز التحقق')
                            ->action('requestEmailChange')
                            ->visible(fn (): bool => ! $this->emailOtpStep)
                            ->color('primary'),

                        Action::make('verifyEmailChange')
                            ->label('تأكيد تغيير البريد')
                            ->action('verifyEmailChange')
                            ->visible(fn (): bool => $this->emailOtpStep)
                            ->color('success'),

                        Action::make('resendEmailChangeCode')
                            ->label('إعادة إرسال الرمز')
                            ->action('resendEmailChangeCode')
                            ->visible(fn (): bool => $this->emailOtpStep)
                            ->color('gray'),

                        Action::make('cancelEmailChange')
                            ->label('إلغاء الطلب')
                            ->action('cancelEmailChange')
                            ->visible(fn (): bool => $this->emailOtpStep)
                            ->color('danger'),
                    ])
                        ->columnSpanFull(),
                ]),

            Section::make('تغيير كلمة المرور')
                ->description('لا تُغيَّر كلمة المرور من زر «حفظ». اترك الحقول فارغة إذا لم ترغب بالتغيير.')
                ->schema([
                    TextInput::make('current_password')
                        ->label('كلمة المرور الحالية')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->autocomplete('current-password'),

                    TextInput::make('password')
                        ->label('كلمة المرور الجديدة')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->autocomplete('new-password'),

                    TextInput::make('password_confirmation')
                        ->label('تأكيد كلمة المرور')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->autocomplete('new-password'),

                    Actions::make([
                        Action::make('changePassword')
                            ->label('تحديث كلمة المرور')
                            ->action('changePassword')
                            ->color('primary'),
                    ])
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('تفضيلات التنبيهات')
                ->description('تظهر التنبيهات دائماً داخل لوحة الإدارة. يمكنك تفعيل أو إيقاف نسخة البريد الإلكتروني.')
                ->schema([
                    Toggle::make('notify_email')
                        ->label('استقبال التنبيهات عبر البريد الإلكتروني')
                        ->default(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        $state = $this->form->getState();

        app(StaffProfileUpdateService::class)->update($user, [
            'name' => $state['name'] ?? '',
            'phone' => $state['phone'] ?? null,
            'staff_photo' => $this->normalizeStaffPhotoState($state['staff_photo'] ?? null),
            'notify_email' => $state['notify_email'] ?? false,
        ]);

        Notification::make()
            ->title('تم حفظ الملف الشخصي')
            ->success()
            ->send();

        $this->fillProfileForm();
    }

    public function requestEmailChange(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        $newEmail = trim((string) ($this->data['new_email'] ?? ''));
        $confirmation = trim((string) ($this->data['new_email_confirmation'] ?? ''));

        $result = app(EmailChangeService::class)->start($user, $newEmail, $confirmation);

        if (! $result['ok']) {
            $field = $result['field'] ?? 'new_email';

            throw ValidationException::withMessages([
                $field === 'email_confirmation' ? 'new_email_confirmation' : 'new_email' => $result['message'],
            ]);
        }

        $this->syncEmailChangeUi();
        $this->clearEmailChangeFields(keepOtp: false);

        Notification::make()
            ->title('تم إرسال رمز التحقق')
            ->body('أدخل الرمز المرسل إلى بريدك الجديد.')
            ->success()
            ->send();
    }

    public function verifyEmailChange(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        $code = trim((string) ($this->data['email_otp'] ?? ''));

        $result = app(EmailChangeService::class)->verify($user, $code);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'email_otp' => $result['message'],
            ]);
        }

        $this->syncEmailChangeUi();
        $this->clearEmailChangeFields(keepOtp: false);
        $this->fillProfileForm();

        Notification::make()
            ->title(EmailChangeService::MSG_SUCCESS)
            ->success()
            ->send();
    }

    public function resendEmailChangeCode(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        $result = app(EmailChangeService::class)->resend($user);

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                'email_otp' => $result['message'],
            ]);
        }

        $this->syncEmailChangeUi();
        $this->data['email_otp'] = '';

        Notification::make()
            ->title('تم إرسال رمز جديد')
            ->success()
            ->send();
    }

    public function cancelEmailChange(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        app(EmailChangeService::class)->cancel($user);

        $this->syncEmailChangeUi();
        $this->clearEmailChangeFields(keepOtp: false);

        Notification::make()
            ->title('تم إلغاء طلب تغيير البريد')
            ->success()
            ->send();
    }

    public function changePassword(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        $current = trim((string) ($this->data['current_password'] ?? ''));
        $password = trim((string) ($this->data['password'] ?? ''));
        $confirmation = trim((string) ($this->data['password_confirmation'] ?? ''));

        if ($current === '' && $password === '' && $confirmation === '') {
            return;
        }

        try {
            Validator::make(
                [
                    'current_password' => $current,
                    'password' => $password,
                    'password_confirmation' => $confirmation,
                ],
                [
                    'current_password' => ['required', 'string'],
                    'password' => AccountPasswordChangeService::newPasswordRules(),
                ],
                [],
                [
                    'current_password' => 'كلمة المرور الحالية',
                    'password' => 'كلمة المرور الجديدة',
                    'password_confirmation' => 'تأكيد كلمة المرور',
                ],
            )->validate();

            app(AccountPasswordChangeService::class)->change(
                $user,
                $current,
                $password,
                session()->getId(),
            );

            Notification::make()
                ->title(AccountPasswordChangeService::MSG_SUCCESS)
                ->success()
                ->send();
        } finally {
            $this->clearPasswordFields();
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('staff-profile-form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky($this->areFormActionsSticky())
                    ->key('staff-profile-form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('حفظ')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    private function syncEmailChangeUi(): void
    {
        $user = auth()->user();
        if ($user === null) {
            $this->emailOtpStep = false;
            $this->maskedPendingEmail = null;

            return;
        }

        $pending = app(EmailChangeService::class)->pendingFor($user);
        $this->emailOtpStep = $pending instanceof PendingEmailChange;
        $this->maskedPendingEmail = $pending instanceof PendingEmailChange
            ? (string) SensitiveContactMasker::maskEmail($pending->pending_email)
            : null;
    }

    private function fillProfileForm(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        $this->form->fill([
            'name' => $user->name,
            'phone' => $user->phone,
            'staff_photo' => $user->staff_photo,
            'notify_email' => (bool) $user->notify_email,
            'new_email' => '',
            'new_email_confirmation' => '',
            'email_otp' => '',
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ]);
    }

    private function clearEmailChangeFields(bool $keepOtp): void
    {
        $this->data['new_email'] = '';
        $this->data['new_email_confirmation'] = '';
        if (! $keepOtp) {
            $this->data['email_otp'] = '';
        }
    }

    private function clearPasswordFields(): void
    {
        $this->data['current_password'] = '';
        $this->data['password'] = '';
        $this->data['password_confirmation'] = '';
    }

    private function normalizeStaffPhotoState(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}

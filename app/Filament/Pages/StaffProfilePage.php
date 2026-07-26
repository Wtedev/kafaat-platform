<?php

namespace App\Filament\Pages;

use App\Services\Media\PublicMediaLifecycleService;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * @property-read Schema $form
 */
class StaffProfilePage extends Page
{
    protected static ?string $slug = 'profile';

    protected static ?string $title = 'الملف الشخصي';

    protected static ?string $navigationLabel = 'الملف الشخصي';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 9999;

    protected static string|\UnitEnum|null $navigationGroup = null;

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

        $user = auth()->user();
        abort_if($user === null, 403);

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'staff_photo' => $user->staff_photo,
            'notify_email' => (bool) $user->notify_email,
            'password' => '',
            'password_confirmation' => '',
        ]);
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

                    TextInput::make('email')
                        ->label('البريد الإلكتروني')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->rule(Rule::unique('users', 'email')->ignore($user->id)),

                    TextInput::make('phone')
                        ->label('رقم الجوال')
                        ->tel()
                        ->maxLength(50)
                        ->nullable(),

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

            Section::make('تفضيلات التنبيهات')
                ->description('تظهر التنبيهات دائماً داخل لوحة الإدارة. يمكنك تفعيل أو إيقاف نسخة البريد الإلكتروني.')
                ->schema([
                    Toggle::make('notify_email')
                        ->label('استقبال التنبيهات عبر البريد الإلكتروني')
                        ->default(false)
                        ->columnSpanFull(),
                ]),

            Section::make('تغيير كلمة المرور')
                ->description('اترك الحقول فارغة إذا لم ترغب بتغيير كلمة المرور.')
                ->schema([
                    TextInput::make('password')
                        ->label('كلمة المرور الجديدة')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->nullable(),

                    TextInput::make('password_confirmation')
                        ->label('تأكيد كلمة المرور')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->nullable(),
                ])
                ->columns(2),
        ]);
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_if($user === null, 403);

        $state = $this->form->getState();
        $password = is_string($this->data['password'] ?? null) ? trim((string) $this->data['password']) : '';
        $passwordConfirmation = is_string($this->data['password_confirmation'] ?? null)
            ? trim((string) $this->data['password_confirmation'])
            : '';

        if ($password !== '') {
            Validator::make(
                ['password' => $password, 'password_confirmation' => $passwordConfirmation],
                [
                    'password' => ['required', 'string', Password::defaults(), 'confirmed'],
                ],
                [],
                [
                    'password' => 'كلمة المرور الجديدة',
                    'password_confirmation' => 'تأكيد كلمة المرور',
                ],
            )->validate();
        }

        $user->name = (string) $state['name'];
        $user->email = (string) $state['email'];
        $user->phone = isset($state['phone']) && $state['phone'] !== '' && $state['phone'] !== null
            ? (string) $state['phone']
            : null;

        $previousStaffPhoto = $user->staff_photo;
        $newStaffPhoto = isset($state['staff_photo']) && $state['staff_photo'] !== ''
            ? (string) $state['staff_photo']
            : null;
        $user->staff_photo = $newStaffPhoto;
        $user->notify_email = (bool) ($state['notify_email'] ?? false);

        if ($password !== '') {
            $user->password = $password;
        }

        $lifecycle = app(PublicMediaLifecycleService::class);

        try {
            $user->save();
        } catch (Throwable $e) {
            if (is_string($newStaffPhoto) && $newStaffPhoto !== $previousStaffPhoto) {
                $lifecycle->discardFailedUpload($newStaffPhoto);
            }
            throw $e;
        }

        $lifecycle->deleteOwnedIfReplaced($previousStaffPhoto, $user->staff_photo);

        Notification::make()
            ->title('تم حفظ الملف الشخصي')
            ->success()
            ->send();

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'staff_photo' => $user->staff_photo,
            'notify_email' => (bool) $user->notify_email,
            'password' => '',
            'password_confirmation' => '',
        ]);
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
}

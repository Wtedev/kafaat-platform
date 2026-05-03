<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
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
                        ->disk('public')
                        ->directory('staff-photos')
                        ->visibility('public')
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),

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
        $user->staff_photo = isset($state['staff_photo']) && $state['staff_photo'] !== ''
            ? (string) $state['staff_photo']
            : null;

        if ($password !== '') {
            $user->password = $password;
        }

        $user->save();

        Notification::make()
            ->title('تم حفظ الملف الشخصي')
            ->success()
            ->send();

        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'staff_photo' => $user->staff_photo,
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

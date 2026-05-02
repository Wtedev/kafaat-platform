<?php

namespace App\Filament\Pages;

use App\Enums\NotificationTargetType;
use App\Enums\RegistrationStatus;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerTeam;
use App\Policies\SendInAppNotificationPolicy;
use App\Services\Inbox\InboxNotificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @property-read Schema $form
 */
class SendInAppNotification extends Page
{
    use CanUseDatabaseTransactions;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'إرسال تنبيه';

    protected static ?string $navigationLabel = 'إرسال تنبيه';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?int $navigationSort = 55;

    protected static string|\UnitEnum|null $navigationGroup = 'الإشعارات';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('accessSendInAppNotificationPage', auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $kinds = app(SendInAppNotificationPolicy::class)->availableTargetKinds(auth()->user());
        $firstKind = array_key_first($kinds) ?? 'user';

        $this->form->fill([
            'target_kind' => $firstKind,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'إرسال تنبيه';
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $policy = app(SendInAppNotificationPolicy::class);

        return $schema->components([
            Section::make('محتوى التنبيه')
                ->schema([
                    TextInput::make('title')
                        ->label('عنوان التنبيه')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('body')
                        ->label('نص التنبيه')
                        ->required()
                        ->rows(6)
                        ->columnSpanFull(),

                    Select::make('target_kind')
                        ->label('نوع الاستهداف')
                        ->options(fn (): array => $policy->availableTargetKinds(auth()->user()))
                        ->required()
                        ->live(),

                    Select::make('target_user_id')
                        ->label('المستخدم')
                        ->options(fn (): array => $policy->eligibleRecipientUsersQuery(auth()->user())
                            ->orderBy('name')
                            ->limit(500)
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('target_kind') === 'user')
                        ->required(fn (Get $get): bool => $get('target_kind') === 'user'),

                    Select::make('target_role')
                        ->label('الدور')
                        ->options(fn (): array => $policy->availableRoleOptions(auth()->user()))
                        ->visible(fn (Get $get): bool => $get('target_kind') === 'role')
                        ->required(fn (Get $get): bool => $get('target_kind') === 'role'),

                    Select::make('target_team_id')
                        ->label('الفريق التطوعي')
                        ->options(fn (): array => $policy->eligibleTeamsQuery(auth()->user())->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('target_kind') === 'team')
                        ->required(fn (Get $get): bool => $get('target_kind') === 'team'),

                    Select::make('target_program_id')
                        ->label('البرنامج التدريبي')
                        ->options(fn (): array => $policy->eligibleTrainingProgramsQuery(auth()->user())->pluck('title', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('target_kind') === 'program')
                        ->required(fn (Get $get): bool => $get('target_kind') === 'program'),
                ])
                ->columns(1),
        ]);
    }

    public function send(): void
    {
        $this->callHook('beforeValidate');

        $data = $this->form->getState();

        $this->callHook('afterValidate');

        $sender = auth()->user();
        if ($sender === null) {
            throw ValidationException::withMessages(['' => 'يجب تسجيل الدخول.']);
        }

        $policy = app(SendInAppNotificationPolicy::class);

        abort_unless($policy->accessPage($sender), 403);

        if (! $policy->canUseTargetKind($sender, (string) $data['target_kind'])) {
            throw ValidationException::withMessages([
                'data.target_kind' => 'نوع الاستهداف غير مسموح لصلاحياتك.',
            ]);
        }

        try {
            $this->beginDatabaseTransaction();

            $inbox = app(InboxNotificationService::class);
            $title = (string) $data['title'];
            $body = (string) $data['body'];

            match ($data['target_kind']) {
                'user' => (function () use ($data, $sender, $policy, $inbox, $title, $body): void {
                    $target = User::query()->findOrFail((int) $data['target_user_id']);
                    if (! $policy->canTargetUser($sender, $target)) {
                        throw ValidationException::withMessages([
                            'data.target_user_id' => 'لا يمكنك إرسال تنبيه إلى هذا المستخدم.',
                        ]);
                    }
                    $inbox->manualGeneral($sender, $title, $body, [$target->id], NotificationTargetType::SingleUser);
                })(),
                'role' => (function () use ($data, $sender, $policy, $inbox, $title, $body): void {
                    $roleKey = (string) $data['target_role'];
                    if (! $policy->canTargetRole($sender, $roleKey)) {
                        throw ValidationException::withMessages([
                            'data.target_role' => 'لا يمكنك الاستهداف حسب هذا الدور.',
                        ]);
                    }
                    $audience = match ($roleKey) {
                        'staff' => NotificationTargetType::Staff,
                        'all_beneficiaries' => NotificationTargetType::AllPortalUsers,
                        'trainees' => NotificationTargetType::Trainees,
                        'volunteers' => NotificationTargetType::Volunteers,
                        default => throw ValidationException::withMessages([
                            'data.target_role' => 'قيمة دور غير صالحة.',
                        ]),
                    };
                    $inbox->generalMessage($sender, $audience, $title, $body);
                })(),
                'team' => (function () use ($data, $sender, $policy, $inbox, $title, $body): void {
                    $team = VolunteerTeam::query()->findOrFail((int) $data['target_team_id']);
                    if (! $policy->canTargetTeam($sender, $team)) {
                        throw ValidationException::withMessages([
                            'data.target_team_id' => 'لا يمكنك إرسال تنبيه إلى هذا الفريق.',
                        ]);
                    }
                    $ids = $team->members()->pluck('user_id')->unique()->values()->all();
                    if ($ids === []) {
                        throw ValidationException::withMessages([
                            'data.target_team_id' => 'الفريق لا يضم أعضاء بعد.',
                        ]);
                    }
                    $inbox->manualGeneral($sender, $title, $body, $ids, NotificationTargetType::VolunteerTeamMembers);
                })(),
                'program' => (function () use ($data, $sender, $policy, $inbox, $title, $body): void {
                    $program = TrainingProgram::query()->findOrFail((int) $data['target_program_id']);
                    if (! $policy->canTargetProgram($sender, $program)) {
                        throw ValidationException::withMessages([
                            'data.target_program_id' => 'لا يمكنك إرسال تنبيه لمستفيدي هذا البرنامج.',
                        ]);
                    }
                    $ids = $program->registrations()
                        ->whereIn('status', [
                            RegistrationStatus::Pending->value,
                            RegistrationStatus::Approved->value,
                        ])
                        ->pluck('user_id')
                        ->unique()
                        ->values()
                        ->all();
                    if ($ids === []) {
                        throw ValidationException::withMessages([
                            'data.target_program_id' => 'لا يوجد مسجّلون (قيد المراجعة أو مقبولون) في هذا البرنامج.',
                        ]);
                    }
                    $inbox->manualGeneral($sender, $title, $body, $ids, NotificationTargetType::ProgramRegistrants);
                })(),
                default => throw ValidationException::withMessages([
                    'data.target_kind' => 'نوع استهداف غير معروف.',
                ]),
            };

            $this->commitDatabaseTransaction();
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        Notification::make()
            ->title('تم الإرسال بنجاح')
            ->success()
            ->send();

        $kind = (string) ($data['target_kind'] ?? 'user');
        $this->form->fill([
            'target_kind' => $kind,
            'title' => null,
            'body' => null,
            'target_user_id' => null,
            'target_role' => null,
            'target_team_id' => null,
            'target_program_id' => null,
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
            ->id('form')
            ->livewireSubmitHandler('send')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('إرسال')
                ->submit('send')
                ->keyBindings(['mod+s']),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }
}

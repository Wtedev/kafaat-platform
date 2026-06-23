<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\UserAccountRoleForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Livewire\Component as LivewireComponent;

class UserResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;
    use RegistersNavigationByPermission;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الوصول';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        if (auth()->user()?->can('roles.view')) {
            return 'إدارة المستخدمين';
        }

        return 'المستفيدين';
    }

    protected static function requiredNavigationPermissions(): array
    {
        return ['users.view'];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('roles');

        $viewer = auth()->user();

        // من لا يملك إدارة الأدوار يرى المستفيدين فقط، لا الموظفين/المسؤولين (حماية PII).
        if ($viewer !== null && ! $viewer->hasPermission('manage_roles')) {
            $query->where(function (Builder $q): void {
                $q->whereIn('role_type', ['beneficiary', 'trainee', 'volunteer'])
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['trainee', 'volunteer']));
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الحساب')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('رقم الجوال')
                            ->tel()
                            ->maxLength(20),

                        Toggle::make('is_active')
                            ->default(true)
                            ->label('نشط'),
                    ]),

                Section::make('الأدوار والصلاحيات')
                    ->description('تعيين نوع الحساب والدور — يتطلب صلاحية إدارة أدوار المستخدمين.')
                    ->visible(function (LivewireComponent $livewire): bool {
                        $actor = auth()->user();
                        if (! $actor?->can('manage_roles')) {
                            return false;
                        }
                        if ($livewire instanceof EditRecord) {
                            $record = $livewire->getRecord();

                            return $record instanceof User && ! $record->isProtectedAdminUser();
                        }

                        return true;
                    })
                    ->schema([
                        Select::make('role_type')
                            ->label('نوع الحساب')
                            ->options(UserAccountRoleForm::accountTypeOptionsAr())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set): void {
                                $set('assigned_role', null);
                            }),

                        Select::make('assigned_role')
                            ->label('الدور')
                            ->options(function (Get $get): array {
                                return match ($get('role_type')) {
                                    UserAccountRoleForm::TYPE_STAFF => UserAccountRoleForm::staffRoleSelectOptionsAr(),
                                    UserAccountRoleForm::TYPE_BENEFICIARY => UserAccountRoleForm::beneficiaryRoleSelectOptionsAr(),
                                    default => [],
                                };
                            })
                            ->visible(fn (Get $get): bool => filled($get('role_type')))
                            ->required(fn (Get $get): bool => filled($get('role_type')))
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account_type_display')
                    ->label('نوع الحساب')
                    ->getStateUsing(fn (User $record): string => UserAccountRoleForm::tableAccountTypeLabelAr($record)),

                TextColumn::make('primary_role_display')
                    ->label('الدور')
                    ->getStateUsing(fn (User $record): string => UserAccountRoleForm::tablePrimaryRoleLabelAr($record)),

                TextColumn::make('phone')
                    ->label('الجوال')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط')
                    ->sortable(),

                TextColumn::make('notify_email')
                    ->label('إشعارات البريد')
                    ->badge()
                    ->getStateUsing(function (User $record): string {
                        if (! filled($record->email)) {
                            return 'بدون بريد';
                        }

                        return $record->notify_email ? 'مفعّل' : 'معطّل';
                    })
                    ->color(function (User $record): string {
                        if (! filled($record->email)) {
                            return 'gray';
                        }

                        return $record->notify_email ? 'success' : 'gray';
                    })
                    ->sortable()
                    ->tooltip(fn (User $record): string => $record->wantsEmailNotifications()
                        ? 'يستقبل نسخة بريدية عند نشر البرامج/المسارات (إذا اختار المنشئ إرسال تنبيه).'
                        : 'لا يستقبل بريداً للتنبيهات العامة؛ التنبيهات داخل المنصة حسب إعداداته.'),

                TextColumn::make('last_login_at')
                    ->label('آخر دخول')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role_type')
                    ->label('نوع الحساب')
                    ->options([
                        'admin' => 'مدير النظام',
                        'staff' => 'موظف',
                        'beneficiary' => 'مستفيد',
                        'trainee' => 'متدرب (قديم)',
                        'volunteer' => 'متطوع (قديم)',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('نشط'),

                TernaryFilter::make('notify_email')
                    ->label('إشعارات البريد')
                    ->trueLabel('مفعّل')
                    ->falseLabel('معطّل')
                    ->placeholder('الكل'),
            ])
            ->actions([
                static::makeTableEditAction(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('users.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('users.update') ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (! $user?->can('users.delete')) {
            return false;
        }

        return $user->can('delete', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

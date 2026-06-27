<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\Concerns\EntityNotesRelationManager;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\UserTechnicalLogRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\UserTrainingRegistrationsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\UserVolunteerRegistrationsRelationManager;
use App\Models\User;
use App\Support\UserAccountRoleForm;
use App\Enums\IdentityType;
use App\Support\UserDirectoryTabs;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Livewire\Component as LivewireComponent;

class UserResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;
    use RegistersNavigationByPermission;

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'المستخدمون';
    }

    protected static function requiredNavigationPermissions(): array
    {
        return ['users.view'];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('roles');

        $viewer = auth()->user();

        if ($viewer !== null && ! $viewer->hasPermission('manage_roles')) {
            $visibleTabs = UserDirectoryTabs::visibleTabKeysFor($viewer);

            if ($visibleTabs === [UserDirectoryTabs::TAB_VOLUNTEERS]) {
                UserDirectoryTabs::applyTabScope($query, UserDirectoryTabs::TAB_VOLUNTEERS);
            } elseif ($visibleTabs === [UserDirectoryTabs::TAB_TRAINEES] || $visibleTabs === []) {
                UserDirectoryTabs::applyTabScope($query, UserDirectoryTabs::TAB_TRAINEES);
            } else {
                $query->where(function (Builder $q): void {
                    UserDirectoryTabs::applyTabScope($q, UserDirectoryTabs::TAB_TRAINEES);
                    $q->orWhere(function (Builder $inner): void {
                        UserDirectoryTabs::applyTabScope($inner, UserDirectoryTabs::TAB_VOLUNTEERS);
                    });
                });
            }
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

                        Toggle::make('notify_email')
                            ->label('إشعارات البريد'),
                    ]),

                Section::make('الدور في المنصة')
                    ->description('اختر دوراً واحداً يحدد صلاحيات الدخول وما يظهر للمستخدم.')
                    ->visible(function (LivewireComponent $livewire): bool {
                        $actor = auth()->user();
                        if ($livewire instanceof EditRecord) {
                            $record = $livewire->getRecord();

                            return $record instanceof User
                                && UserAccountRoleForm::canActorEditRoleSection($actor, $record);
                        }

                        return UserAccountRoleForm::canActorEditRoleSection($actor);
                    })
                    ->schema([
                        Select::make('platform_role')
                            ->label('الدور')
                            ->options(fn (): array => UserAccountRoleForm::platformRoleOptionsForActor(auth()->user()))
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->helperText(fn (): string => UserAccountRoleForm::actorCanManageAllPlatformRoles(auth()->user())
                                ? 'موظفون يدخلون لوحة الإدارة؛ المتدرب والمتطوع يدخلون بوابة المستفيد.'
                                : 'يمكنك تعيين متدرب أو متطوع فقط.')
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
            ->columns([
                TextColumn::make('full_name_display')
                    ->label('الاسم')
                    ->getStateUsing(fn (User $record): string => $record->fullName())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('father_name', 'like', "%{$search}%")
                                ->orWhere('grandfather_name', 'like', "%{$search}%")
                                ->orWhere('family_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('first_name', $direction)
                            ->orderBy('family_name', $direction);
                    }),

                TextColumn::make('identity_type')
                    ->label('نوع الهوية')
                    ->formatStateUsing(fn (?IdentityType $state): string => $state?->label() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('masked_identity')
                    ->label('رقم الهوية')
                    ->getStateUsing(fn (User $record): string => $record->maskedIdentityNumber() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('profile_completion')
                    ->label('اكتمال البيانات')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => $record->hasCompletedRequiredIdentityData() ? 'مكتمل' : 'ناقص')
                    ->color(fn (User $record): string => $record->hasCompletedRequiredIdentityData() ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('platform_role_display')
                    ->label('الدور في المنصة')
                    ->getStateUsing(fn (User $record): string => UserAccountRoleForm::tablePlatformRoleLabelAr($record)),

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

    protected static function resolveEditOnlyRecordUrl(Model $record): ?string
    {
        if ($record instanceof User && $record->isPortalUser() && static::hasPage('view') && (auth()->user()?->can('users.view') ?? false)) {
            return static::getUrl('view', ['record' => $record]);
        }

        if (static::hasPage('edit') && static::canEdit($record)) {
            return static::getUrl('edit', ['record' => $record]);
        }

        if (static::hasPage('view') && (auth()->user()?->can('users.view') ?? false)) {
            return static::getUrl('view', ['record' => $record]);
        }

        return null;
    }

    public static function getRelations(): array
    {
        return [
            UserTrainingRegistrationsRelationManager::class,
            UserVolunteerRegistrationsRelationManager::class,
            UserTechnicalLogRelationManager::class,
            EntityNotesRelationManager::class,
        ];
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

    public static function beneficiaryCvPdfUrl(User $user): string
    {
        return route('admin.beneficiaries.cv-pdf', ['user' => $user]);
    }
}

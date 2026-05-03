<?php

namespace App\Filament\Resources;

use App\Enums\MembershipType;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\ProfileResource\Pages;
use App\Models\Profile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ProfileResource extends Resource
{
    use RegistersNavigationByPermission;

    protected static ?string $model = Profile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الوصول';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'الملفات الشخصية';

    protected static ?string $modelLabel = 'ملف شخصي';

    protected static ?string $pluralModelLabel = 'الملفات الشخصية';

    protected static ?string $recordTitleAttribute = 'user.name';

    protected static function requiredNavigationPermissions(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('roles.view') || $user->can('edit_profile_badges'));
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('roles.view');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        $invalidMessage = 'القيمة المحددة غير صحيحة.';

        return $schema->components([
            Section::make('بيانات الملف الشخصي')
                ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view'))
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->label('المستخدم')
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

                    FileUpload::make('avatar')
                        ->label('الصورة الشخصية')
                        ->image()
                        ->disk('public')
                        ->directory('avatars')
                        ->visibility('public')
                        ->nullable(),

                    Select::make('membership_type')
                        ->label('نوع العضوية')
                        ->options([
                            MembershipType::Beneficiary->value => 'مستفيد',
                            MembershipType::Trainee->value => 'متدرب',
                            MembershipType::Volunteer->value => 'متطوع',
                        ])
                        ->default(MembershipType::Beneficiary->value)
                        ->required()
                        ->native(false),

                    Textarea::make('bio')
                        ->label('السيرة الذاتية')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull()
                        ->nullable(),
                ]),

            Section::make('شارات المستفيد')
                ->description('شارات العرض في بوابة المستفيد (لا تغيّر أدوار Spatie).')
                ->visible(fn (): bool => (bool) auth()->user()?->can('edit_profile_badges'))
                ->schema([
                    CheckboxList::make('membership_badges')
                        ->label('نوع المستفيد')
                        ->options([
                            'trainee' => 'متدرب',
                            'volunteer' => 'متطوع',
                        ])
                        ->default([])
                        ->columns(2)
                        ->helperText('ستظهر شارة مستفيد بشكل افتراضي، ويمكن إضافة متدرب أو متطوع أو كلاهما.')
                        ->rules(['nullable', 'array'])
                        ->nestedRecursiveRules([
                            Rule::in(['trainee', 'volunteer']),
                        ])
                        ->validationMessages([
                            'membership_badges.*.in' => 'القيمة المحددة غير صحيحة.',
                        ]),

                    TextInput::make('iconic_skill')
                        ->label('المهارة الأيقونية')
                        ->placeholder('مثال: قائد مبادر، صانع أثر، متميز في التواصل')
                        ->helperText('اترك الحقل فارغًا لعرض: لا يوجد مهارة أيقونية')
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
                        ->columnSpanFull(),

                    Select::make('iconic_skill_style')
                        ->label('لون شارة المهارة')
                        ->options([
                            'amber' => 'ذهبي',
                            'emerald' => 'أخضر',
                            'sky' => 'أزرق',
                            'rose' => 'وردي',
                            'violet' => 'بنفسجي',
                            'brand' => 'لون الهوية',
                        ])
                        ->default('amber')
                        ->native(false)
                        ->nullable()
                        ->live()
                        ->visible(fn (Get $get): bool => filled(trim((string) ($get('iconic_skill') ?? ''))))
                        ->rules([
                            'nullable',
                            Rule::in(Profile::allowedIconicSkillStyles()),
                        ])
                        ->validationMessages([
                            'iconic_skill_style.in' => $invalidMessage,
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('display_membership_badges')
                    ->label('شارات نوع المستفيد')
                    ->getStateUsing(fn (Profile $record): string => implode(' + ', $record->displayMembershipBadges()))
                    ->toggleable(),

                TextColumn::make('gender')
                    ->label('الجنس')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                        default => '-',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('city')
                    ->label('المدينة')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('membership_type')
                    ->label('نوع العضوية')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state instanceof MembershipType) {
                            return $state->label();
                        }
                        if (is_string($state) && $state !== '') {
                            return MembershipType::tryFrom($state)?->label() ?? 'مستفيد';
                        }

                        return 'مستفيد';
                    })
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('iconic_skill')
                    ->label('المهارة الأيقونية')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? (string) $state : 'لا يوجد مهارة أيقونية')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('birth_date')
                    ->label('تاريخ الميلاد')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gender')
                    ->label('الجنس')
                    ->options([
                        'male' => 'ذكر',
                        'female' => 'أنثى',
                    ]),
                SelectFilter::make('membership_type')
                    ->label('نوع العضوية')
                    ->options([
                        MembershipType::Beneficiary->value => 'مستفيد',
                        MembershipType::Trainee->value => 'متدرب',
                        MembershipType::Volunteer->value => 'متطوع',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => (bool) auth()->user()?->can('roles.view')),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfiles::route('/'),
            'create' => Pages\CreateProfile::route('/create'),
            'view' => Pages\ViewProfile::route('/{record}'),
            'edit' => Pages\EditProfile::route('/{record}/edit'),
        ];
    }
}

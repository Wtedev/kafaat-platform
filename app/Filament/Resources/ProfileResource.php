<?php

namespace App\Filament\Resources;

use App\Enums\MembershipType;
use App\Filament\Resources\ProfileResource\Pages;
use App\Models\Profile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProfileResource extends Resource
{
    protected static ?string $model = Profile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الوصول';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'الملفات الشخصية';

    protected static ?string $modelLabel = 'ملف شخصي';

    protected static ?string $pluralModelLabel = 'الملفات الشخصية';

    protected static ?string $recordTitleAttribute = 'user.name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الملف الشخصي')
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

                    TextInput::make('iconic_skill')
                        ->label('المهارة الأيقونية')
                        ->helperText('تظهر في واجهة المستفيد عند إضافتها فقط')
                        ->maxLength(120)
                        ->nullable()
                        ->columnSpanFull(),

                    Textarea::make('bio')
                        ->label('السيرة الذاتية')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull()
                        ->nullable(),
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
                    ->toggleable(),

                TextColumn::make('iconic_skill')
                    ->label('المهارة الأيقونية')
                    ->limit(40)
                    ->placeholder('—')
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
                    DeleteBulkAction::make(),
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

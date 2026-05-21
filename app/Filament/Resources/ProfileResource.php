<?php

namespace App\Filament\Resources;

use App\Enums\MembershipType;
use App\Filament\Concerns\ConfiguresEditOnlyResourceTable;
use App\Filament\Concerns\RegistersNavigationByPermission;
use App\Filament\Resources\ProfileResource\Pages;
use App\Filament\Resources\ProfileResource\Schemas\ProfileAdminForm;
use App\Models\Profile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProfileResource extends Resource
{
    use ConfiguresEditOnlyResourceTable;
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
        return ProfileAdminForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return static::applyEditOnlyTable($table)
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
                    ->label('شارات العضوية')
                    ->getStateUsing(fn (Profile $record): string => implode(' · ', $record->displayMembershipBadges()))
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
                    ->label('المهارة المميزة')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? (string) $state : '—')
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
                static::makeTableEditAction(),
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
            'edit' => Pages\EditProfile::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Support;

use App\Enums\IdentityCategory;
use App\Enums\ProfileGender;
use App\Enums\RegistrationStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class ProgramRegistrationsTableFilters
{
    /**
     * @return array<int, Filter|SelectFilter>
     */
    public static function make(): array
    {
        return [
            self::nationalityFilter(),
            SelectFilter::make('status')
                ->label('حالة القبول')
                ->options(RegistrationStatus::class),
            self::genderFilter(),
            self::ageFilter(),
        ];
    }

    public static function nationalityFilter(): SelectFilter
    {
        return SelectFilter::make('nationality')
            ->label('الجنسية')
            ->options([
                'saudi' => 'سعودي',
                'non_saudi' => 'غير سعودي',
                'unspecified' => 'غير محدد',
            ])
            ->query(function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null;
                if (! filled($value)) {
                    return $query;
                }

                if ($value === 'unspecified') {
                    return $query->whereHas(
                        'user',
                        fn (Builder $user): Builder => $user->whereNull('identity_category'),
                    );
                }

                $category = match ($value) {
                    'saudi' => IdentityCategory::Saudi->value,
                    'non_saudi' => IdentityCategory::Resident->value,
                    default => null,
                };

                if ($category === null) {
                    return $query;
                }

                return $query->whereHas(
                    'user',
                    fn (Builder $user): Builder => $user->where('identity_category', $category),
                );
            });
    }

    private static function genderFilter(): SelectFilter
    {
        return SelectFilter::make('gender')
            ->label('الجنس')
            ->options([
                'male' => ProfileGender::Male->label(),
                'female' => ProfileGender::Female->label(),
                'unspecified' => 'غير محدد',
            ])
            ->query(function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null;
                if (! filled($value)) {
                    return $query;
                }

                if ($value === 'unspecified') {
                    return $query->where(function (Builder $match): void {
                        $match->whereDoesntHave('user.profile')
                            ->orWhereHas('user.profile', fn (Builder $profile): Builder => $profile->whereNull('gender'));
                    });
                }

                return $query->whereHas(
                    'user.profile',
                    fn (Builder $profile): Builder => $profile->where('gender', $value),
                );
            });
    }

    private static function ageFilter(): Filter
    {
        return Filter::make('age')
            ->label('العمر')
            ->form([
                Select::make('preset')
                    ->label('نطاق سريع')
                    ->options([
                        'under_18' => 'أقل من 18',
                        '18_24' => '18–24',
                        '25_34' => '25–34',
                        '35_plus' => '35 فأكثر',
                        'unspecified' => 'غير محدد',
                    ])
                    ->placeholder('—')
                    ->live(),
                TextInput::make('min')
                    ->label('من (سنوات)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(120)
                    ->live(onBlur: true),
                TextInput::make('max')
                    ->label('إلى (سنوات)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(120)
                    ->live(onBlur: true),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $preset = $data['preset'] ?? null;
                $min = filled($data['min'] ?? null) ? (int) $data['min'] : null;
                $max = filled($data['max'] ?? null) ? (int) $data['max'] : null;

                if ($preset === 'unspecified') {
                    return $query->where(function (Builder $match): void {
                        $match->whereDoesntHave('user.profile')
                            ->orWhereHas('user.profile', fn (Builder $profile): Builder => $profile->whereNull('birth_date'));
                    });
                }

                [$rangeMin, $rangeMax] = match ($preset) {
                    'under_18' => [null, 17],
                    '18_24' => [18, 24],
                    '25_34' => [25, 34],
                    '35_plus' => [35, null],
                    default => [$min, $max],
                };

                if ($rangeMin === null && $rangeMax === null) {
                    return $query;
                }

                $today = Carbon::today();

                return $query->whereHas('user.profile', function (Builder $profile) use ($today, $rangeMin, $rangeMax): void {
                    $profile->whereNotNull('birth_date');

                    if ($rangeMin !== null) {
                        // age >= min ⇒ birth_date <= today - min years
                        $profile->whereDate('birth_date', '<=', $today->copy()->subYears($rangeMin));
                    }

                    if ($rangeMax !== null) {
                        // age <= max ⇒ birth_date > today - (max+1) years
                        $profile->whereDate('birth_date', '>', $today->copy()->subYears($rangeMax + 1));
                    }
                });
            })
            ->indicateUsing(function (array $data): array {
                $labels = [];
                if (filled($data['preset'] ?? null)) {
                    $labels[] = match ($data['preset']) {
                        'under_18' => 'العمر: أقل من 18',
                        '18_24' => 'العمر: 18–24',
                        '25_34' => 'العمر: 25–34',
                        '35_plus' => 'العمر: 35 فأكثر',
                        'unspecified' => 'العمر: غير محدد',
                        default => null,
                    };
                }
                if (filled($data['min'] ?? null) || filled($data['max'] ?? null)) {
                    $labels[] = 'العمر: '.($data['min'] ?? '…').'–'.($data['max'] ?? '…');
                }

                return array_values(array_filter($labels));
            });
    }
}

<?php

namespace App\Support;

use App\Models\TrainingProgram;
use Carbon\Carbon;

/**
 * Public sidebar period breakdown for «قادة التطوع».
 */
final class VolunteerLeadersProgramPeriod
{
    public const TITLE_NEEDLE = 'قادة التطوع';

    public const PARTNER_ADEED_LOGO = 'images/programs/adeed-logo.png';

    public const PARTNER_KAFAAT_LOGO = 'images/programs/partner-kafaat.svg';

    public const PARTNER_ASSOCIATIONS_SUPPORT_FUND_LOGO = 'images/programs/partner-associations-support-fund.png';

    public const PARTNER_HR_MINISTRY_LOGO = 'images/programs/partner-hr-ministry.png';

    public const PARTNER_NONPROFIT_CENTER_LOGO = 'images/programs/partner-nonprofit-center.png';

    public const PARTNER_MASARAT_RAEDA_LOGO = 'images/programs/partner-masarat-raeda.png';

    public const PARTNER_BAYT_AL_THAQAFA_LOGO = 'images/programs/partner-bayt-al-thaqafa.png';

    /**
     * @return list<array{heading: string, partners: list<array{name: string, logo: string, alt: string}>}>
     */
    public static function programPartnerGroups(): array
    {
        return [
            [
                'heading' => 'مالك البرنامج',
                'partners' => [
                    [
                        'name' => 'جمعية عضيد للخدمات التطوعية',
                        'logo' => self::PARTNER_ADEED_LOGO,
                        'alt' => 'شعار جمعية عضيد للخدمات التطوعية',
                    ],
                ],
            ],
            [
                'heading' => 'الشريك المنفذ',
                'partners' => [
                    [
                        'name' => 'جمعية كفاءات',
                        'logo' => self::PARTNER_KAFAAT_LOGO,
                        'alt' => 'شعار جمعية كفاءات',
                    ],
                ],
            ],
            [
                'heading' => 'الشريك الداعم',
                'partners' => [
                    [
                        'name' => 'صندوق دعم الجمعيات',
                        'logo' => self::PARTNER_ASSOCIATIONS_SUPPORT_FUND_LOGO,
                        'alt' => 'شعار صندوق دعم الجمعيات',
                    ],
                ],
            ],
            [
                'heading' => 'الشريك الاستراتيجي',
                'partners' => [
                    [
                        'name' => 'وزارة الموارد البشرية',
                        'logo' => self::PARTNER_HR_MINISTRY_LOGO,
                        'alt' => 'شعار وزارة الموارد البشرية',
                    ],
                    [
                        'name' => 'المركز الوطني لتنمية القطاع غير الربحي',
                        'logo' => self::PARTNER_NONPROFIT_CENTER_LOGO,
                        'alt' => 'شعار المركز الوطني لتنمية القطاع غير الربحي',
                    ],
                ],
            ],
            [
                'heading' => 'شركاء النجاح',
                'partners' => [
                    [
                        'name' => 'مسارات رائدة',
                        'logo' => self::PARTNER_MASARAT_RAEDA_LOGO,
                        'alt' => 'شعار مسارات رائدة',
                    ],
                    [
                        'name' => 'بيت الثقافة',
                        'logo' => self::PARTNER_BAYT_AL_THAQAFA_LOGO,
                        'alt' => 'شعار بيت الثقافة',
                    ],
                ],
            ],
        ];
    }

    /**
     * In-person calendar days within the program window (Y-m-d).
     *
     * @var list<string>
     */
    public const IN_PERSON_DATES = [
        '2026-08-03',
        '2026-08-04',
        '2026-08-05',
        '2026-08-16',
        '2026-08-17',
        '2026-08-18',
    ];

    public static function applies(?TrainingProgram $program): bool
    {
        if ($program === null) {
            return false;
        }

        return str_contains((string) $program->title, self::TITLE_NEEDLE);
    }

    /**
     * Display label for in-person days: «3–5 أغسطس، 16–18 أغسطس».
     */
    public static function inPersonDaysLabel(): string
    {
        return en_digits('3–5 أغسطس، 16–18 أغسطس');
    }

    public static function remoteDaysLabel(): string
    {
        return 'المتبقي من أيام الفترة';
    }

    /**
     * Groups consecutive August days: «3–5، 16–18 أغسطس 2026».
     */
    public static function formatInPersonDatesLabel(): string
    {
        $groups = self::inPersonDayGroups();
        $dates = self::sortedInPersonDates();

        if ($groups === [] || $dates === []) {
            return '';
        }

        $monthYear = ar_date($dates[0], 'MMM y');

        return implode('، ', $groups).' '.$monthYear;
    }

    /**
     * @return list<string> e.g. ['3–5', '16–18']
     */
    public static function inPersonDayGroups(): array
    {
        $dates = self::sortedInPersonDates();

        if ($dates === []) {
            return [];
        }

        $groups = [];
        $groupStart = $dates[0];
        $groupEnd = $dates[0];

        for ($i = 1; $i < count($dates); $i++) {
            $current = $dates[$i];
            if ($groupEnd->copy()->addDay()->equalTo($current)) {
                $groupEnd = $current;

                continue;
            }

            $groups[] = self::formatDayGroup($groupStart, $groupEnd);
            $groupStart = $current;
            $groupEnd = $current;
        }

        $groups[] = self::formatDayGroup($groupStart, $groupEnd);

        return $groups;
    }

    /**
     * @return list<Carbon>
     */
    private static function sortedInPersonDates(): array
    {
        return collect(self::IN_PERSON_DATES)
            ->map(fn (string $d): Carbon => Carbon::parse($d)->startOfDay())
            ->sortBy(fn (Carbon $d): int => $d->timestamp)
            ->values()
            ->all();
    }

    private static function formatDayGroup(Carbon $start, Carbon $end): string
    {
        if ($start->equalTo($end)) {
            return (string) $start->day;
        }

        return $start->day.'–'.$end->day;
    }
}

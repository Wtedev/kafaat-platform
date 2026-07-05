<?php

namespace App\Support\Format;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use IntlDateFormatter;

final class LocaleFormat
{
    /** @var list<string> */
    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** @var list<string> */
    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function toLatinDigits(string $value): string
    {
        return str_replace(self::ARABIC_DIGITS, self::LATIN_DIGITS, str_replace('٪', '%', $value));
    }

    public static function number(float|int|string|null $value, int $decimals = 0, string $decimalSeparator = '.', string $thousandsSeparator = ','): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::toLatinDigits(number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator));
    }

    public static function date(DateTimeInterface|CarbonInterface|string|null $value, string $pattern = 'd MMMM y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value);

        $formatter = new IntlDateFormatter(
            'ar_SA@numbers=latn',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->timezoneName ?? config('app.timezone'),
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        $formatted = $formatter->format($date);

        return is_string($formatted) ? self::toLatinDigits($formatted) : '';
    }

    public static function dateTime(DateTimeInterface|CarbonInterface|string|null $value): string
    {
        return self::date($value, 'd MMMM y — HH:mm');
    }

    /** Maps common Carbon translatedFormat patterns used in the app. */
    public static function fromCarbonFormat(DateTimeInterface|CarbonInterface|string|null $value, string $carbonFormat): string
    {
        $pattern = match ($carbonFormat) {
            'j F Y' => 'd MMMM y',
            'j F Y، H:i', 'j F Y — H:i' => 'd MMMM y — HH:mm',
            'F Y' => 'MMMM y',
            default => 'd MMMM y',
        };

        return self::date($value, $pattern);
    }
}

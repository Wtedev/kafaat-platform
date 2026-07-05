<?php

use App\Support\Format\LocaleFormat;

if (! function_exists('en_digits')) {
    function en_digits(string $value): string
    {
        return LocaleFormat::toLatinDigits($value);
    }
}

if (! function_exists('en_num')) {
    function en_num(float|int|string|null $value, int $decimals = 0): string
    {
        return LocaleFormat::number($value, $decimals);
    }
}

if (! function_exists('ar_date')) {
    function ar_date(\DateTimeInterface|\Carbon\CarbonInterface|string|null $value, string $pattern = 'd MMMM y'): string
    {
        return LocaleFormat::date($value, $pattern);
    }
}

if (! function_exists('ar_date_time')) {
    function ar_date_time(\DateTimeInterface|\Carbon\CarbonInterface|string|null $value): string
    {
        return LocaleFormat::dateTime($value);
    }
}

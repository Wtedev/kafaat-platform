<?php

namespace App\Support\Exports;

/**
 * Prevent spreadsheet formula injection when cell values begin with formula triggers.
 */
final class ExcelFormulaInjectionGuard
{
    public static function sanitize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return $value;
        }

        $first = $value[0];

        if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

<?php

namespace App\Support;

/**
 * Lightweight Arabic folding for search (hamza variants, tāʾ marbūṭa, alif maqṣūra).
 */
final class ArabicText
{
    /**
     * @var array<string, string>
     */
    private const FOLD_MAP = [
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
        'ٱ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
        'ؤ' => 'و',
        'ئ' => 'ي',
    ];

    public static function fold(string $value): string
    {
        return strtr($value, self::FOLD_MAP);
    }

    /**
     * SQL expression that folds Arabic characters in a column (SQLite + PostgreSQL).
     */
    public static function sqlFoldColumn(string $columnSql): string
    {
        $expr = $columnSql;

        foreach (self::FOLD_MAP as $from => $to) {
            $expr = 'REPLACE('.$expr.', '.self::quote($from).', '.self::quote($to).')';
        }

        return $expr;
    }

    private static function quote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}

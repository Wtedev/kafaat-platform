<?php

namespace App\Support;

/**
 * يحوّل قيمة DB_DATABASE لاتصال SQLite إلى مسار ملف آمن.
 * الاسم النسبي (مثل postgres) يُحفظ داخل database/ ولا يُنشأ في جذر المشروع.
 */
final class SqliteDatabasePath
{
    public static function resolve(?string $database): string
    {
        $database = is_string($database) ? trim($database) : '';

        if ($database === '') {
            return database_path('database.sqlite');
        }

        if ($database === ':memory:' || str_contains($database, 'mode=memory')) {
            return $database;
        }

        if (str_starts_with($database, 'file:')) {
            return str_starts_with($database, 'file:/')
                ? $database
                : database_path('database.sqlite');
        }

        if (self::isAbsolute($database)) {
            return $database;
        }

        $relative = str_replace('\\', '/', $database);

        while (str_starts_with($relative, './')) {
            $relative = substr($relative, 2);
        }

        if (str_starts_with($relative, 'database/')) {
            $relative = substr($relative, strlen('database/'));
        }

        if ($relative === '' || str_contains($relative, "\0") || preg_match('#(^|/)\.\.(/|$)#', $relative) === 1) {
            return database_path('database.sqlite');
        }

        return database_path($relative);
    }

    private static function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}

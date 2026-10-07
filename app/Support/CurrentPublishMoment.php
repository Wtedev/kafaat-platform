<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * موعد النشر الحالي هو اليوم حسب توقيت المنصة.
 * المواعيد الأقدم تُنشر عند اللحاق بها بلا إشعار إطلاق.
 */
final class CurrentPublishMoment
{
    public static function includes(?CarbonInterface $publishedAt): bool
    {
        if ($publishedAt === null || $publishedAt->greaterThan(now())) {
            return false;
        }

        return $publishedAt->greaterThanOrEqualTo(now()->startOfDay());
    }
}

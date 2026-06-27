<?php

namespace App\Data\Privacy\Export;

use App\Models\User;
use App\Models\UserActivityLog;

final readonly class ActivityExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return UserActivityLog::query()
            ->where('user_id', $user->id)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (UserActivityLog $log): array => [
                'title' => $log->title,
                'category' => $log->action?->category(),
                'action' => $log->action?->value,
                'detail' => $log->detail,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}

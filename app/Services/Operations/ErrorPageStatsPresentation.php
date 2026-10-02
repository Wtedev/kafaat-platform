<?php

namespace App\Services\Operations;

use App\Models\ErrorPageVisit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class ErrorPageStatsPresentation
{
    public function __construct(
        private readonly ErrorPageVisitRecorder $recorder,
    ) {}

    /**
     * @return array<int, string>
     */
    public function statusLabels(): array
    {
        return [
            403 => 'غير مصرح',
            404 => 'غير موجود',
            419 => 'انتهت الجلسة',
            429 => 'طلبات كثيرة',
            500 => 'خطأ خادم',
            502 => 'بوابة',
            503 => 'غير متاح',
            504 => 'انتهاء المهلة',
            505 => 'إصدار HTTP',
        ];
    }

    /**
     * @param  list<array{date?: string, hits?: int}>  $daily
     */
    public function chartMax(array $daily): int
    {
        $rows = $daily !== [] ? $daily : [['hits' => 0]];
        $hits = array_map(fn (array $day): int => (int) ($day['hits'] ?? 0), $rows);

        return max(1, ...$hits);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon, 2: ?int, 3: ?string}
     */
    public function parseFilters(?string $from, ?string $to, ?string $status, string $url): array
    {
        return [
            filled($from) ? Carbon::parse($from) : null,
            filled($to) ? Carbon::parse($to) : null,
            filled($status) ? (int) $status : null,
            filled($url) ? trim($url) : null,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, ErrorPageVisit>
     */
    public function recentVisits(?Carbon $from, ?Carbon $to, ?int $status, ?string $url): LengthAwarePaginator
    {
        $query = ErrorPageVisit::query()->with(['user:id,name,email']);
        $this->recorder->applyFilters($query, $from, $to, $status, $url);

        return $query
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(?Carbon $from, ?Carbon $to, ?int $status, ?string $url): array
    {
        return $this->recorder->summarize(
            now: now(),
            from: $from,
            to: $to,
            status: $status,
            url: $url,
        );
    }

    public function pruneOlderThanDays(int $days): int
    {
        return $this->recorder->pruneOlderThan($days);
    }

    public function canAccess(?User $user): bool
    {
        return $user instanceof User && $user->canAccessFilamentAdmin();
    }
}

<?php

namespace App\Services\Operations;

use App\Models\ErrorPageHit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorPageHitRecorder
{
    /** @var list<int> */
    public const TRACKED_STATUSES = [404, 500, 502, 503, 504, 505];

    /** HTTP 500 + uncommon 505 HTTP Version Not Supported */
    public const SERVER_ERROR_STATUSES = [500, 505];

    /** Gateway / unavailable — closest Laravel-side analogue to Railway «Application failed to respond» */
    public const GATEWAY_STATUSES = [502, 503, 504];

    public function shouldTrack(int $status): bool
    {
        return in_array($status, self::TRACKED_STATUSES, true);
    }

    public function record(int $status, ?Carbon $at = null): void
    {
        if (! $this->shouldTrack($status)) {
            return;
        }

        $day = ($at ?? now())->toDateString();

        try {
            $updated = ErrorPageHit::query()
                ->where('status', $status)
                ->whereDate('day', $day)
                ->increment('hits');

            if ($updated === 0) {
                try {
                    ErrorPageHit::query()->create([
                        'status' => $status,
                        'day' => $day,
                        'hits' => 1,
                    ]);
                } catch (Throwable) {
                    ErrorPageHit::query()
                        ->where('status', $status)
                        ->whereDate('day', $day)
                        ->increment('hits');
                }
            }
        } catch (Throwable $e) {
            // Never let metrics break the real error response.
            Log::debug('error_page_hit.record_failed', [
                'status' => $status,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *     not_found: int,
     *     server_error: int,
     *     gateway: int,
     *     today: array{not_found: int, server_error: int, gateway: int},
     *     by_status: array<int, int>,
     *     today_by_status: array<int, int>
     * }
     */
    public function summarize(?Carbon $today = null): array
    {
        $today = ($today ?? now())->toDateString();

        $byStatus = ErrorPageHit::query()
            ->select('status', DB::raw('SUM(hits) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $todayByStatus = ErrorPageHit::query()
            ->whereDate('day', $today)
            ->pluck('hits', 'status')
            ->map(fn ($v): int => (int) $v)
            ->all();

        return [
            'not_found' => $this->sumStatuses($byStatus, [404]),
            'server_error' => $this->sumStatuses($byStatus, self::SERVER_ERROR_STATUSES),
            'gateway' => $this->sumStatuses($byStatus, self::GATEWAY_STATUSES),
            'today' => [
                'not_found' => $this->sumStatuses($todayByStatus, [404]),
                'server_error' => $this->sumStatuses($todayByStatus, self::SERVER_ERROR_STATUSES),
                'gateway' => $this->sumStatuses($todayByStatus, self::GATEWAY_STATUSES),
            ],
            'by_status' => $byStatus,
            'today_by_status' => $todayByStatus,
        ];
    }

    /**
     * @param  array<int|string, int>  $counts
     * @param  list<int>  $statuses
     */
    private function sumStatuses(array $counts, array $statuses): int
    {
        $total = 0;
        foreach ($statuses as $status) {
            $total += (int) ($counts[$status] ?? 0);
        }

        return $total;
    }
}

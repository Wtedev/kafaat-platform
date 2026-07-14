<?php

namespace App\Services\Operations;

use App\Models\ErrorPageVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorPageVisitRecorder
{
    public const REQUEST_FLAG = 'error_page_visit_recorded';

    public const EXCEPTION_ATTRIBUTE = 'error_page_exception';

    /** @var list<int> */
    public const TRACKED_STATUSES = [403, 404, 419, 429, 500, 502, 503, 504, 505];

    /** Spec-required visitor statuses (HTML error pages). */
    public const PRIMARY_STATUSES = [403, 404, 419, 429, 500, 503];

    /** @var list<string> */
    private const SENSITIVE_QUERY_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
        'access_token',
        'refresh_token',
        'api_key',
        'apikey',
        'api-key',
        'secret',
        'client_secret',
        'authorization',
        'auth',
        'key',
        'code',
        'otp',
        'otp_code',
        'session',
        'session_id',
        'remember',
        'remember_token',
        'signature',
        'hash',
        'csrf',
    ];

    /** @var list<string> */
    private const NOISY_PATH_SUFFIXES = [
        '.map',
        '.css.map',
        '.js.map',
        'favicon.ico',
        'favicon.png',
        'robots.txt',
        'apple-touch-icon.png',
        'apple-touch-icon-precomposed.png',
    ];

    public function shouldTrack(int $status): bool
    {
        return in_array($status, self::TRACKED_STATUSES, true);
    }

    public function recordFromResponse(Request $request, Response $response, ?Throwable $exception = null): void
    {
        if (! $this->shouldTrack($response->getStatusCode())) {
            return;
        }

        if (! $this->shouldRecordRequest($request, $response)) {
            return;
        }

        $exception ??= $request->attributes->get(self::EXCEPTION_ATTRIBUTE);
        $exceptionClass = $exception instanceof Throwable ? $exception::class : null;

        $this->record([
            'status_code' => $response->getStatusCode(),
            'requested_url' => $this->sanitizeUrl($request),
            'route_name' => optional($request->route())->getName(),
            'request_method' => strtoupper($request->getMethod()),
            'ip_address' => $request->ip(),
            'user_agent' => $this->truncate($request->userAgent(), 512),
            'referer' => $this->sanitizeReferer($request->headers->get('referer')),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'exception_class' => $exceptionClass,
        ], $request);
    }

    /**
     * @param  array{
     *     status_code: int,
     *     requested_url: string,
     *     route_name: ?string,
     *     request_method: string,
     *     ip_address: ?string,
     *     user_agent: ?string,
     *     referer: ?string,
     *     user_id: int|string|null,
     *     exception_class: ?string
     * }  $payload
     */
    public function record(array $payload, ?Request $request = null): void
    {
        $request ??= request();

        if ($request instanceof Request && $request->attributes->get(self::REQUEST_FLAG) === true) {
            return;
        }

        if (! $this->shouldTrack((int) ($payload['status_code'] ?? 0))) {
            return;
        }

        if ($request instanceof Request) {
            $request->attributes->set(self::REQUEST_FLAG, true);
        }

        try {
            ErrorPageVisit::query()->create([
                'status_code' => (int) $payload['status_code'],
                'requested_url' => $this->truncate((string) ($payload['requested_url'] ?? '/'), 2048) ?? '/',
                'route_name' => $this->truncate($payload['route_name'] ?? null, 255),
                'request_method' => $this->truncate((string) ($payload['request_method'] ?? 'GET'), 16) ?? 'GET',
                'ip_address' => $this->truncate($payload['ip_address'] ?? null, 45),
                'user_agent' => $this->truncate($payload['user_agent'] ?? null, 512),
                'referer' => $this->truncate($payload['referer'] ?? null, 2048),
                'user_id' => $payload['user_id'] ?? null,
                'exception_class' => $this->truncate($payload['exception_class'] ?? null, 255),
            ]);
        } catch (Throwable $e) {
            Log::debug('error_page_visit.record_failed', [
                'status_code' => $payload['status_code'] ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function shouldRecordRequest(Request $request, Response $response): bool
    {
        if ($request->is('up')) {
            return false;
        }

        if ($this->isNoisyStaticPath($request->path())) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return false;
        }

        return true;
    }

    public function sanitizeUrl(Request $request): string
    {
        $path = '/'.ltrim($request->path(), '/');
        if ($path === '//') {
            $path = '/';
        }

        $query = $request->query();
        if (! is_array($query) || $query === []) {
            return $path === '' ? '/' : $path;
        }

        $clean = [];
        foreach ($query as $key => $value) {
            $normalized = strtolower((string) $key);
            if ($this->isSensitiveQueryKey($normalized)) {
                $clean[(string) $key] = '[redacted]';

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[(string) $key] = $value;

                continue;
            }

            $clean[(string) $key] = '[filtered]';
        }

        $qs = http_build_query($clean);

        return $qs === '' ? $path : $path.'?'.$qs;
    }

    public function pruneOlderThan(int $days): int
    {
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        try {
            return ErrorPageVisit::query()
                ->where('created_at', '<', $cutoff)
                ->delete();
        } catch (Throwable $e) {
            Log::debug('error_page_visit.prune_failed', [
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @return array{
     *     total: int,
     *     today: int,
     *     last_7_days: int,
     *     last_30_days: int,
     *     by_status: array<int, int>,
     *     top_urls: list<array{url: string, hits: int}>,
     *     top_404s: list<array{url: string, hits: int}>,
     *     daily: list<array{date: string, hits: int}>
     * }
     */
    public function summarize(?Carbon $now = null, ?Carbon $from = null, ?Carbon $to = null, ?int $status = null, ?string $url = null): array
    {
        $now ??= now();
        $base = ErrorPageVisit::query();
        $this->applyFilters($base, $from, $to, $status, $url);

        $total = (clone $base)->count();

        $todayStart = $now->copy()->startOfDay();
        $last7 = $now->copy()->subDays(6)->startOfDay();
        $last30 = $now->copy()->subDays(29)->startOfDay();

        $unfiltered = ErrorPageVisit::query();
        $this->applyFilters($unfiltered, null, null, $status, $url);

        return [
            'total' => $total,
            'today' => (clone $unfiltered)->where('created_at', '>=', $todayStart)->count(),
            'last_7_days' => (clone $unfiltered)->where('created_at', '>=', $last7)->count(),
            'last_30_days' => (clone $unfiltered)->where('created_at', '>=', $last30)->count(),
            'by_status' => $this->groupCount((clone $base), 'status_code'),
            'top_urls' => $this->topUrls((clone $base), limit: 10),
            'top_404s' => $this->topUrls(
                (clone $base)->where('status_code', 404),
                limit: 10,
            ),
            'daily' => $this->dailySeries((clone $base), $now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()),
        ];
    }

    /**
     * @param  Builder<ErrorPageVisit>  $query
     */
    public function applyFilters($query, ?Carbon $from, ?Carbon $to, ?int $status, ?string $url): void
    {
        if ($from !== null) {
            $query->where('created_at', '>=', $from->copy()->startOfDay());
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to->copy()->endOfDay());
        }

        if ($status !== null) {
            $query->where('status_code', $status);
        }

        if ($url !== null && trim($url) !== '') {
            $query->where('requested_url', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($url)).'%');
        }
    }

    /**
     * @param  Builder<ErrorPageVisit>  $query
     * @return array<int, int>
     */
    private function groupCount($query, string $column): array
    {
        return $query
            ->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->pluck('total', $column)
            ->map(fn ($v): int => (int) $v)
            ->all();
    }

    /**
     * @param  Builder<ErrorPageVisit>  $query
     * @return list<array{url: string, hits: int}>
     */
    private function topUrls($query, int $limit): array
    {
        return $query
            ->select('requested_url', DB::raw('COUNT(*) as hits'))
            ->groupBy('requested_url')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'url' => (string) $row->requested_url,
                'hits' => (int) $row->hits,
            ])
            ->all();
    }

    /**
     * @param  Builder<ErrorPageVisit>  $query
     * @return list<array{date: string, hits: int}>
     */
    private function dailySeries($query, Carbon $from, Carbon $to): array
    {
        $rows = $query
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as hits'))
            ->groupBy('day')
            ->pluck('hits', 'day')
            ->map(fn ($v): int => (int) $v)
            ->all();

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'hits' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $series;
    }

    private function isSensitiveQueryKey(string $key): bool
    {
        if (in_array($key, self::SENSITIVE_QUERY_KEYS, true)) {
            return true;
        }

        foreach (['password', 'token', 'secret', 'authorization'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isNoisyStaticPath(string $path): bool
    {
        $lower = strtolower($path);

        foreach (self::NOISY_PATH_SUFFIXES as $suffix) {
            if ($lower === $suffix || str_ends_with($lower, '/'.$suffix) || str_ends_with($lower, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeReferer(?string $referer): ?string
    {
        if ($referer === null || $referer === '') {
            return null;
        }

        $parts = parse_url($referer);
        if ($parts === false) {
            return '[invalid]';
        }

        $path = $parts['path'] ?? '/';
        $host = $parts['host'] ?? null;
        $scheme = $parts['scheme'] ?? null;
        $base = ($scheme && $host) ? $scheme.'://'.$host.$path : $path;

        if (! isset($parts['query']) || $parts['query'] === '') {
            return $this->truncate($base, 2048);
        }

        parse_str($parts['query'], $query);
        $clean = [];
        foreach ($query as $key => $value) {
            $normalized = strtolower((string) $key);
            if ($this->isSensitiveQueryKey($normalized)) {
                $clean[(string) $key] = '[redacted]';
            } elseif (is_scalar($value) || $value === null) {
                $clean[(string) $key] = $value;
            } else {
                $clean[(string) $key] = '[filtered]';
            }
        }

        $qs = http_build_query($clean);

        return $this->truncate($qs === '' ? $base : $base.'?'.$qs, 2048);
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Operations\ErrorPageHitRecorder;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ErrorPageStatsPage extends Page
{
    protected static ?string $slug = 'error-page-stats';

    protected static ?string $navigationLabel = 'إحصاءات صفحات الأخطاء';

    protected static ?string $title = 'إحصاءات صفحات الأخطاء';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.error-page-stats';

    /**
     * @var array{
     *     not_found: int,
     *     server_error: int,
     *     gateway: int,
     *     today: array{not_found: int, server_error: int, gateway: int},
     *     by_status: array<int, int>,
     *     today_by_status: array<int, int>
     * }
     */
    public array $stats = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canAccessFilamentAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->stats = app(ErrorPageHitRecorder::class)->summarize();
    }

    public function getTitle(): string|Htmlable
    {
        return 'إحصاءات صفحات الأخطاء';
    }

    public function refreshStats(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->stats = app(ErrorPageHitRecorder::class)->summarize();
    }
}

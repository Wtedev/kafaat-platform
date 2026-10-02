<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BelongsToStaffUiModule;
use App\Models\ErrorPageVisit;
use App\Models\User;
use App\Services\Operations\ErrorPageStatsPresentation;
use App\Support\StaffUi\StaffUiModule;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\WithPagination;

class ErrorPageStatsPage extends Page
{
    use BelongsToStaffUiModule;
    use WithPagination;

    protected static function staffUiModule(): string
    {
        return StaffUiModule::SUPPORT;
    }

    protected static ?string $slug = 'error-page-stats';

    protected static ?string $navigationLabel = 'إحصاءات صفحات الأخطاء';

    protected static ?string $title = 'إحصاءات صفحات الأخطاء';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'الأمان والامتثال';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.error-page-stats';

    public ?string $filterFrom = null;

    public ?string $filterTo = null;

    public ?string $filterStatus = null;

    public string $filterUrl = '';

    /**
     * @var array{
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
    public array $stats = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return app(ErrorPageStatsPresentation::class)->canAccess($user instanceof User ? $user : null);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->refreshStats();
    }

    public function getTitle(): string|Htmlable
    {
        return 'إحصاءات صفحات الأخطاء';
    }

    public function updatedFilterFrom(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedFilterTo(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedFilterUrl(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function applyFilters(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetPage();
        $this->refreshStats();
    }

    public function clearFilters(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->filterFrom = null;
        $this->filterTo = null;
        $this->filterStatus = null;
        $this->filterUrl = '';
        $this->resetPage();
        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        abort_unless(static::canAccess(), 403);

        [$from, $to, $status, $url] = $this->parsedFilters();

        $this->stats = app(ErrorPageStatsPresentation::class)->summarize($from, $to, $status, $url);
    }

    /**
     * @return LengthAwarePaginator<int, ErrorPageVisit>
     */
    public function getRecentVisitsProperty(): LengthAwarePaginator
    {
        [$from, $to, $status, $url] = $this->parsedFilters();

        return app(ErrorPageStatsPresentation::class)->recentVisits($from, $to, $status, $url);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon, 2: ?int, 3: ?string}
     */
    private function parsedFilters(): array
    {
        return app(ErrorPageStatsPresentation::class)->parseFilters(
            $this->filterFrom,
            $this->filterTo,
            $this->filterStatus,
            $this->filterUrl,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('تحديث')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->refreshStats()),

            Action::make('prune')
                ->label('حذف السجلات القديمة')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('حذف السجلات القديمة؟')
                ->modalDescription('سيتم حذف زيارات صفحات الأخطاء الأقدم من 90 يومًا. لا يمكن التراجع عن هذا الإجراء.')
                ->modalSubmitActionLabel('حذف')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);

                    $deleted = app(ErrorPageStatsPresentation::class)->pruneOlderThanDays(90);

                    Notification::make()
                        ->title('تم حذف السجلات القديمة')
                        ->body("عدد السجلات المحذوفة: {$deleted}")
                        ->success()
                        ->send();

                    $this->resetPage();
                    $this->refreshStats();
                }),
        ];
    }
}

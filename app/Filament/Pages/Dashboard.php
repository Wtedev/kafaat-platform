<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\BelongsToStaffUiModule;
use App\Http\Controllers\StaffUi\StaffDashboardController;
use App\Models\User;
use App\Support\StaffUi\StaffUiAccess;
use App\Support\StaffUi\StaffUiModule;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class Dashboard extends BaseDashboard
{
    use BelongsToStaffUiModule;

    /**
     * Admins see the new shell. Every other staff user keeps the Filament dashboard.
     */
    public function __invoke(): Response|View
    {
        $user = auth()->user();

        if ($user instanceof User && StaffUiAccess::seesNewUi($user)) {
            return app(StaffDashboardController::class)(request());
        }

        return parent::__invoke();
    }

    protected static function staffUiModule(): string
    {
        return StaffUiModule::SHELL;
    }

    public static function isDiscovered(): bool
    {
        return false;
    }
}

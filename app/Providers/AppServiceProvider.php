<?php

namespace App\Providers;

use App\Models\InboxNotification;
use App\Models\User;
use App\Policies\InboxNotificationPolicy;
use App\Policies\UserPolicy;
use App\Services\Inbox\InboxNotificationService;
use App\Services\Rbac\RbacService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RbacService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(InboxNotification::class, InboxNotificationPolicy::class);

        Gate::define('accessSendInAppNotificationPage', function (?User $user): bool {
            return $user !== null && $user->can('send_notifications');
        });

        View::composer('layouts.portal', function ($view): void {
            if (! auth()->check()) {
                return;
            }

            $view->with(
                'portalInboxUnreadCount',
                app(InboxNotificationService::class)->unreadCount(auth()->user()),
            );
        });
    }
}

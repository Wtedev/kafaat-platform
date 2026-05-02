<?php

namespace App\Providers;

use App\Models\InboxNotification;
use App\Models\News;
use App\Models\Profile;
use App\Models\User;
use App\Policies\InboxNotificationPolicy;
use App\Policies\NewsPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\SendInAppNotificationPolicy;
use App\Policies\UserPolicy;
use App\Services\Inbox\InboxNotificationService;
use App\Services\News\NewsPublicationService;
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
        $this->app->singleton(NewsPublicationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(InboxNotification::class, InboxNotificationPolicy::class);
        Gate::policy(News::class, NewsPolicy::class);

        Gate::define('accessSendInAppNotificationPage', function (?User $user): bool {
            if ($user === null || ! $user->is_active) {
                return false;
            }

            return app(SendInAppNotificationPolicy::class)->accessPage($user);
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

<?php

namespace App\Providers;

use App\Models\BoardMember;
use App\Models\GovernanceDocument;
use App\Models\InboxNotification;
use App\Models\MediaPhoto;
use App\Models\News;
use App\Models\Profile;
use App\Models\Regulation;
use App\Models\User;
use App\Policies\BoardMemberPolicy;
use App\Policies\GovernanceDocumentPolicy;
use App\Policies\InboxNotificationPolicy;
use App\Policies\MediaPhotoPolicy;
use App\Policies\NewsPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\RegulationPolicy;
use App\Policies\SendInAppNotificationPolicy;
use App\Policies\UserPolicy;
use App\Services\Inbox\InboxNotificationService;
use App\Services\News\NewsPublicationService;
use App\Services\Rbac\RbacService;
use App\Services\UserActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
        $this->configureEmailVerificationOnLogin();
        $this->configureUserActivityLogging();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(InboxNotification::class, InboxNotificationPolicy::class);
        Gate::policy(News::class, NewsPolicy::class);
        Gate::policy(Regulation::class, RegulationPolicy::class);
        Gate::policy(GovernanceDocument::class, GovernanceDocumentPolicy::class);
        Gate::policy(BoardMember::class, BoardMemberPolicy::class);
        Gate::policy(MediaPhoto::class, MediaPhotoPolicy::class);

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

    private function configureEmailVerificationOnLogin(): void
    {
        // يغطي /login و /admin/login والتسجيل: يُرسل رمز OTP جديد في كل تسجيل دخول
        // ويعيد ضبط بوابة الجلسة، فيُطلب الرمز من الجميع (مستفيد/موظف/أدمن) في كل مرة.
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;

            if (! ($user instanceof MustVerifyEmail)) {
                return;
            }

            session()->put('otp_verified', false);

            if (method_exists($user, 'sendEmailVerificationNotification')) {
                $user->sendEmailVerificationNotification();
            }
        });
    }

    private function configureUserActivityLogging(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                UserActivityLogger::logLogin($event->user);
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                UserActivityLogger::logLogout($event->user);
            }
        });
    }

    private function configureRateLimiting(): void
    {
        // تسجيل الدخول: 5 محاولات لكل IP كل دقيقة
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return back()
                        ->withInput()
                        ->withErrors(['email' => 'لقد تجاوزت عدد المحاولات المسموح بها. حاول مجدداً بعد دقيقة.']);
                });
        });

        // إنشاء الحساب: 3 محاولات لكل IP كل دقيقة
        RateLimiter::for('register', function (Request $request): Limit {
            return Limit::perMinute(3)
                ->by($request->ip())
                ->response(function () {
                    return back()
                        ->withInput()
                        ->withErrors(['email' => 'لقد تجاوزت عدد طلبات إنشاء الحساب. حاول مجدداً بعد دقيقة.']);
                });
        });

        // نسيت كلمة المرور: 5 طلبات لكل IP كل 5 دقائق
        RateLimiter::for('forgot-password', function (Request $request): Limit {
            return Limit::perMinutes(5, 5)
                ->by($request->ip())
                ->response(function () {
                    return back()
                        ->withInput()
                        ->withErrors(['email' => 'لقد تجاوزت عدد الطلبات المسموح بها. حاول مجدداً بعد قليل.']);
                });
        });
    }
}

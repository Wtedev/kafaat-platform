<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\BoardMember;
use App\Models\InvestmentDecisionYear;
use App\Models\GovernanceCommittee;
use App\Models\GovernanceDocument;
use App\Models\InboxNotification;
use App\Models\MediaPhoto;
use App\Models\News;
use App\Models\PrivacyPolicyVersion;
use App\Models\PrivacyRequest;
use App\Models\Profile;
use App\Models\Regulation;
use App\Models\RetentionException;
use App\Models\RetentionPolicy;
use App\Models\RetentionRun;
use App\Models\SecurityLog;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\BoardMemberPolicy;
use App\Policies\GovernanceCommitteePolicy;
use App\Policies\GovernanceDocumentPolicy;
use App\Policies\InvestmentDecisionYearPolicy;
use App\Policies\InboxNotificationPolicy;
use App\Policies\MediaPhotoPolicy;
use App\Policies\NewsPolicy;
use App\Policies\PrivacyPolicyVersionPolicy;
use App\Policies\PrivacyRequestPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\RegulationPolicy;
use App\Policies\RetentionExceptionPolicy;
use App\Policies\RetentionPolicyPolicy;
use App\Policies\RetentionRunPolicy;
use App\Policies\SendInAppNotificationPolicy;
use App\Policies\SecurityLogPolicy;
use App\Policies\UserPolicy;
use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Services\Security\SecurityLogService;
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
use Illuminate\Support\Facades\Log;
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
        $this->app->singleton(\App\Services\Privacy\Retention\RetentionResourceCatalog::class);
        $this->app->singleton(\App\Services\Privacy\Retention\RetentionHandlerRegistry::class);
        $this->app->singleton(\App\Services\Privacy\Retention\RetentionPolicyEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureProductionHttps();
        $this->configureEmailVerificationOnLogin();
        $this->configureUserActivityLogging();
        $this->configureSecurityLogging();
        $this->configureAdminGateBypass();

        Gate::policy(PrivacyPolicyVersion::class, PrivacyPolicyVersionPolicy::class);
        Gate::policy(PrivacyRequest::class, PrivacyRequestPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Profile::class, ProfilePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(SecurityLog::class, SecurityLogPolicy::class);
        Gate::policy(InboxNotification::class, InboxNotificationPolicy::class);
        Gate::policy(News::class, NewsPolicy::class);
        Gate::policy(RetentionPolicy::class, RetentionPolicyPolicy::class);
        Gate::policy(RetentionException::class, RetentionExceptionPolicy::class);
        Gate::policy(RetentionRun::class, RetentionRunPolicy::class);
        Gate::policy(Regulation::class, RegulationPolicy::class);
        Gate::policy(GovernanceDocument::class, GovernanceDocumentPolicy::class);
        Gate::policy(GovernanceCommittee::class, GovernanceCommitteePolicy::class);
        Gate::policy(InvestmentDecisionYear::class, InvestmentDecisionYearPolicy::class);
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

            $consentService = app(\App\Services\CandidatePool\CandidatePoolConsentService::class);
            $view->with('showCandidatePoolPrompt', $consentService->shouldPrompt(auth()->user()));
            $view->with('candidatePoolConsentText', $consentService->consentText());
        });
    }

    private function configureProductionHttps(): void
    {
        $root = rtrim((string) config('app.url'), '/');
        if ($root !== '') {
            \Illuminate\Support\Facades\URL::forceRootUrl($root);
        }

        if (config('security.force_https', false)) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
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
                try {
                    $user->sendEmailVerificationNotification();
                } catch (\Throwable $exception) {
                    Log::error('otp.send_failed', [
                        'user_id' => $user->getAuthIdentifier(),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }

    private function configureSecurityLogging(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! ($event->user instanceof User)) {
                return;
            }

            app(SecurityLogService::class)->record(
                'auth.login_succeeded',
                SecurityLogResult::Success,
                SecurityLogSeverity::Info,
                $event->user,
                request: request(),
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! ($event->user instanceof User)) {
                return;
            }

            app(SecurityLogService::class)->record(
                'auth.logout',
                SecurityLogResult::Success,
                SecurityLogSeverity::Info,
                $event->user,
                request: request(),
            );
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

    /**
     * حساب الأدمن الوحيد يتجاوز فحوصات الصلاحيات الدقيقة (باستثناء حذف حساب الأدمن المحمي).
     */
    private function configureAdminGateBypass(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! $user instanceof User || ! $user->isAdmin()) {
                return null;
            }

            $target = $arguments[0] ?? null;
            if ($ability === 'delete' && $target instanceof User && $target->isProtectedAdminUser()) {
                return false;
            }

            return true;
        });
    }

    private function configureRateLimiting(): void
    {
        // تسجيل الدخول: 5 محاولات لكل IP+بريد كل دقيقة (يقلل التشويه خلف NAT مع TrustProxies *)
        RateLimiter::for('login', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));
            $key = $email !== '' ? $email.'|'.$request->ip() : (string) $request->ip();

            return Limit::perMinute(5)
                ->by($key)
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

        // نسيت كلمة المرور: 5 طلبات لكل بريد+IP كل 5 دقائق
        RateLimiter::for('forgot-password', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));
            $key = $email !== '' ? $email.'|'.$request->ip() : (string) $request->ip();

            return Limit::perMinutes(5, 5)
                ->by($key)
                ->response(function () {
                    return back()
                        ->withInput()
                        ->withErrors(['email' => 'لقد تجاوزت عدد الطلبات المسموح بها. حاول مجدداً بعد قليل.']);
                });
        });

        RateLimiter::for('certificate-verify', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('privacy-request', function (Request $request): Limit {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(5)
                ->by((string) $key)
                ->response(function () {
                    return back()->withErrors(['email' => 'لقد تجاوزت عدد طلبات الخصوصية المسموح بها. حاول لاحقاً.']);
                });
        });

        RateLimiter::for('privacy-export-download', function (Request $request): Limit {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(10)
                ->by((string) $key)
                ->response(function () {
                    return back()->withErrors(['export' => 'لقد تجاوزت عدد محاولات التنزيل. حاول لاحقاً.']);
                });
        });

        RateLimiter::for('support-ticket', function (Request $request): Limit {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinutes(10, 5)
                ->by((string) $key)
                ->response(function () {
                    return back()
                        ->withInput()
                        ->withErrors(['body' => 'لقد تجاوزت عدد التذاكر المسموح بها مؤقتاً. حاول مجدداً بعد قليل.']);
                });
        });
    }
}

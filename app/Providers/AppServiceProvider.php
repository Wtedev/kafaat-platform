<?php

namespace App\Providers;

use App\Auth\EloquentUserProvider;
use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\AuditLog;
use App\Models\BoardMember;
use App\Models\GovernanceCommittee;
use App\Models\GovernanceDocument;
use App\Models\InboxNotification;
use App\Models\InvestmentDecisionYear;
use App\Models\MediaPhoto;
use App\Models\News;
use App\Models\PrivacyPolicyVersion;
use App\Models\PrivacyRequest;
use App\Models\Profile;
use App\Models\ProgramBroadcast;
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
use App\Policies\InboxNotificationPolicy;
use App\Policies\InvestmentDecisionYearPolicy;
use App\Policies\MediaPhotoPolicy;
use App\Policies\NewsPolicy;
use App\Policies\PrivacyPolicyVersionPolicy;
use App\Policies\PrivacyRequestPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\ProgramBroadcastPolicy;
use App\Policies\RegulationPolicy;
use App\Policies\RetentionExceptionPolicy;
use App\Policies\RetentionPolicyPolicy;
use App\Policies\RetentionRunPolicy;
use App\Policies\SecurityLogPolicy;
use App\Policies\SendInAppNotificationPolicy;
use App\Policies\UserPolicy;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\Inbox\InboxNotificationService;
use App\Services\News\NewsPublicationService;
use App\Services\Privacy\Retention\RetentionHandlerRegistry;
use App\Services\Privacy\Retention\RetentionPolicyEngine;
use App\Services\Privacy\Retention\RetentionResourceCatalog;
use App\Services\Rbac\RbacService;
use App\Services\Security\SecurityLogService;
use App\Services\UserActivityLogger;
use App\Support\Auth\EmailNormalizer;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
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
        $this->app->singleton(RetentionResourceCatalog::class);
        $this->app->singleton(RetentionHandlerRegistry::class);
        $this->app->singleton(RetentionPolicyEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthUserProvider();
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
        Gate::policy(ProgramBroadcast::class, ProgramBroadcastPolicy::class);
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

            $consentService = app(CandidatePoolConsentService::class);
            $view->with('showCandidatePoolPrompt', $consentService->shouldPrompt(auth()->user()));
            $view->with('candidatePoolConsentText', $consentService->consentText());
        });
    }

    private function configureProductionHttps(): void
    {
        // Always emit Vite build URLs as root-relative paths so CSS/JS stay
        // same-origin under CSP even if the request host briefly differs from
        // APP_URL (local localhost↔127.0.0.1, or Railway default hostname).
        Vite::createAssetPathsUsing(fn (string $path, ?bool $secure = null) => '/'.ltrim($path, '/'));

        // Local/testing: do not pin asset()/url() to APP_URL. Browsing
        // http://127.0.0.1 while APP_URL is http://localhost (or the reverse)
        // makes Filament/public asset() URLs cross-origin; CSP then blocks them.
        if ($this->app->environment(['local', 'testing'])) {
            return;
        }

        $root = rtrim((string) config('app.url'), '/');
        if ($root !== '') {
            URL::forceRootUrl($root);
        }

        if (config('security.force_https', false)) {
            URL::forceScheme('https');
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

            // Signup OTP already verified ownership; skip a second login OTP for this session only.
            if (session()->pull('auth.skip_login_otp') === true) {
                session()->put('otp_verified', true);

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

    private function configureAuthUserProvider(): void
    {
        Auth::provider('eloquent', function ($app, array $config) {
            return new EloquentUserProvider($app['hash'], $config['model']);
        });
    }

    private function configureRateLimiting(): void
    {
        // تسجيل الدخول: 5 محاولات لكل IP+بريد كل دقيقة (يقلل التشويه خلف NAT مع TrustProxies *)
        RateLimiter::for('login', function (Request $request): Limit {
            $email = EmailNormalizer::normalize((string) $request->input('email', ''));
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

        RateLimiter::for('signup-verify', function (Request $request): Limit {
            return Limit::perMinute(6)
                ->by((string) $request->ip())
                ->response(function () {
                    return back()->withErrors(['code' => 'لقد تجاوزت عدد محاولات التحقق. حاول مجدداً بعد دقيقة.']);
                });
        });

        RateLimiter::for('signup-resend', function (Request $request): Limit {
            return Limit::perMinutes(10, 5)
                ->by((string) $request->ip())
                ->response(function () {
                    return back()->withErrors(['code' => 'لقد تجاوزت عدد طلبات إعادة الإرسال. حاول مجدداً لاحقاً.']);
                });
        });

        // نسيت كلمة المرور: 5 طلبات لكل بريد+IP كل 5 دقائق
        RateLimiter::for('forgot-password', function (Request $request): Limit {
            $email = EmailNormalizer::normalize((string) $request->input('email', ''));
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

        RateLimiter::for('email-change-send', function (Request $request): Limit {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinutes(10, 5)
                ->by('email-change-send:'.$key)
                ->response(function () {
                    return back()
                        ->with('email_change_open', true)
                        ->withErrors(['email' => 'تم تجاوز عدد المحاولات المسموح بها، يرجى المحاولة لاحقًا.']);
                });
        });

        RateLimiter::for('email-change-resend', function (Request $request): Limit {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinutes(10, 5)
                ->by('email-change-resend:'.$key)
                ->response(function () {
                    return back()
                        ->with('email_change_step', 'otp')
                        ->withErrors(['code' => 'تم تجاوز عدد المحاولات المسموح بها، يرجى المحاولة لاحقًا.']);
                });
        });

        RateLimiter::for('email-change-verify', function (Request $request): Limit {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinutes(10, 20)
                ->by('email-change-verify:'.$key)
                ->response(function () {
                    return back()
                        ->with('email_change_step', 'otp')
                        ->withErrors(['code' => 'تم تجاوز عدد المحاولات المسموح بها، يرجى المحاولة لاحقًا.']);
                });
        });
    }
}

<?php

use App\Http\Controllers\Admin\BeneficiaryCvFileDownloadController;
use App\Http\Controllers\Admin\BeneficiaryCvPdfController;
use App\Http\Controllers\Admin\BeneficiaryIdentityRevealController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\EmailVerificationNoticeController;
use App\Http\Controllers\Auth\EmailVerificationResendController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CertificateDownloadController;
use App\Http\Controllers\Gate\GateAttendanceController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\Portal\PortalAttendanceCheckInController;
use App\Http\Controllers\Portal\PortalAttendanceSessionController;
use App\Http\Controllers\Portal\PortalCertificateController;
use App\Http\Controllers\Portal\PortalCandidatePoolConsentController;
use App\Http\Controllers\Portal\PortalCandidatePoolSettingsController;
use App\Http\Controllers\Portal\PortalCompetencyController;
use App\Http\Controllers\Portal\PortalCompetencyExportController;
use App\Http\Controllers\Portal\PortalCvDocumentController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalInboxController;
use App\Http\Controllers\Portal\PortalPathController;
use App\Http\Controllers\Portal\PortalPathDetailController;
use App\Http\Controllers\Portal\PortalPrivacyPolicyAcknowledgeController;
use App\Http\Controllers\Portal\PortalPasswordController;
use App\Http\Controllers\Portal\PortalProfileCompleteController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\PortalSettingsController;
use App\Http\Controllers\Portal\PortalProgramController;
use App\Http\Controllers\Portal\PortalProgramDetailController;
use App\Http\Controllers\Portal\PortalVolunteerController;
use App\Http\Controllers\PublicPrivacyPolicyController;
use App\Http\Controllers\Public\CertificateVerificationController;
use App\Http\Controllers\Public\HomeController;
use App\Enums\CompetencyTrack;
use App\Http\Controllers\Public\PublicCompetencyTracksController;
use App\Http\Controllers\Public\PublicGovernanceController;
use App\Http\Controllers\Public\PublicLearningPathController;
use App\Http\Controllers\Public\PublicMediaController;
use App\Http\Controllers\Public\PublicNewsController;
use App\Http\Controllers\Public\PublicRegulationController;
use App\Http\Controllers\Public\PublicTrainingProgramController;
use App\Http\Controllers\Public\PublicVolunteerOpportunityController;
use App\Http\Controllers\Public\SupportTicketController;
use Illuminate\Support\Facades\Route;

// ─── Authentication ───────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

    // Password Reset
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email')->middleware('throttle:forgot-password');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// Email Verification
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', EmailVerificationNoticeController::class)->name('verification.notice');
    Route::post('/email/verify', EmailVerificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.verify');
    Route::post('/email/verification-notification', EmailVerificationResendController::class)
        ->middleware('throttle:3,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'otp.verified'])->group(function () {
    Route::get('/certificates/{certificate}/download', CertificateDownloadController::class)
        ->name('certificates.download');

    Route::get('/admin/beneficiaries/{user}/cv-pdf', BeneficiaryCvPdfController::class)
        ->name('admin.beneficiaries.cv-pdf');

    Route::get('/admin/beneficiaries/{user}/cv/download', BeneficiaryCvFileDownloadController::class)
        ->name('admin.beneficiaries.cv-file.download');

    Route::post('/admin/beneficiaries/{user}/identity/reveal', BeneficiaryIdentityRevealController::class)
        ->middleware('throttle:10,1')
        ->name('admin.beneficiaries.identity.reveal');

    // تفضيل إشعارات البريد (النافذة المنبثقة لمرة واحدة) — متاح لكل المستخدمين.
    Route::post('/notification-prefs/ack', [NotificationPreferenceController::class, 'acknowledge'])
        ->name('notification-prefs.ack');
});

// ─── Public website ───────────────────────────────────────────────────────────

Route::get('/', HomeController::class)->name('home');

Route::post('/support-tickets', [SupportTicketController::class, 'store'])
    ->middleware('throttle:support-ticket')
    ->name('public.support-tickets.store');

// Certificate verification (public, no auth required)
Route::get('/certificates/verify/{code}', CertificateVerificationController::class)
    ->middleware('throttle:certificate-verify')
    ->name('certificates.verify');

// ─── Gate QR attendance (in-person programs) ──────────────────────────────────

Route::prefix('gate/{program:slug}')->name('gate.')->group(function () {
    Route::get('/', [GateAttendanceController::class, 'login'])->name('login');
    Route::post('/login', [GateAttendanceController::class, 'authenticate'])
        ->middleware('throttle:12,1')
        ->name('login.store');

    Route::middleware('gate.attendance')->group(function () {
        Route::get('/scan', [GateAttendanceController::class, 'scan'])->name('scan');
        Route::post('/scan', [GateAttendanceController::class, 'mark'])
            ->middleware('throttle:60,1')
            ->name('scan.store');
        Route::post('/logout', [GateAttendanceController::class, 'logout'])->name('logout');
    });
});

Route::prefix('paths')->name('public.paths.')->group(function () {
    Route::get('/', [PublicLearningPathController::class, 'index'])->name('index');
    Route::get('/{learningPath:slug}', [PublicLearningPathController::class, 'show'])->name('show');
    Route::post('/{learningPath:slug}/register', [PublicLearningPathController::class, 'register'])->middleware('auth')->name('register');
});

Route::get('/tracks', PublicCompetencyTracksController::class)->name('public.tracks.index');

Route::prefix('programs')->name('public.programs.')->group(function () {
    Route::get('/', [PublicTrainingProgramController::class, 'index'])->name('index');
    Route::get('/{track}', [PublicTrainingProgramController::class, 'track'])
        ->whereIn('track', array_column(CompetencyTrack::cases(), 'value'))
        ->name('track');
    Route::get('/{trainingProgram:slug}', [PublicTrainingProgramController::class, 'show'])->name('show');
    Route::post('/{trainingProgram:slug}/register', [PublicTrainingProgramController::class, 'register'])->middleware('auth')->name('register');
    Route::get('/{trainingProgram:slug}/registered/{registration}', [PublicTrainingProgramController::class, 'registered'])
        ->middleware('auth')
        ->name('registered');
});

Route::prefix('volunteering')->name('public.volunteering.')->group(function () {
    Route::get('/', [PublicVolunteerOpportunityController::class, 'index'])->name('index');
    Route::get('/{volunteerOpportunity:slug}', [PublicVolunteerOpportunityController::class, 'show'])->name('show');
    Route::post('/{volunteerOpportunity:slug}/register', [PublicVolunteerOpportunityController::class, 'register'])->middleware('auth')->name('register');
});

Route::prefix('news')->name('public.news.')->group(function () {
    Route::get('/', [PublicNewsController::class, 'index'])->name('index');
    Route::get('/{news:slug}', [PublicNewsController::class, 'show'])->name('show');
});

Route::get('/regulations', PublicRegulationController::class)->name('public.regulations.index');

Route::get('/governance', PublicGovernanceController::class)->name('public.governance.index');

Route::get('/media', PublicMediaController::class)->name('public.media.index');

Route::get('/privacy', [PublicPrivacyPolicyController::class, 'current'])->name('public.privacy');
Route::get('/privacy/versions/{version}', [PublicPrivacyPolicyController::class, 'version'])->name('public.privacy.version');
Route::view('/terms', 'public.terms')->name('public.terms');

// ─── Beneficiary Portal ───────────────────────────────────────────────────────

Route::middleware(['auth', 'otp.verified', 'beneficiary', 'privacy.acknowledged'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {

        Route::get('/', PortalDashboardController::class)->name('dashboard');

        Route::get('/notifications', [PortalInboxController::class, 'index'])->name('notifications');
        Route::get('/notifications/settings', [PortalInboxController::class, 'settings'])->name('notifications.settings');
        Route::patch('/notifications/settings', [PortalInboxController::class, 'updateSettings'])->name('notifications.settings.update');
        Route::post('/notifications/read-all', [PortalInboxController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [PortalInboxController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/{notification}/registration-action', [PortalInboxController::class, 'registrationAction'])
            ->name('notifications.registration-action');
        Route::get('/paths', PortalPathController::class)->name('paths');
        Route::get('/paths/{learningPath}', PortalPathDetailController::class)->name('paths.show');
        Route::post('/paths/{learningPath}/attendance/check-in', [PortalAttendanceCheckInController::class, 'checkInPath'])
            ->name('paths.attendance.check-in');
        Route::get('/paths/{learningPath}/attendance/session', [PortalAttendanceSessionController::class, 'path'])
            ->name('paths.attendance.session');
        Route::get('/programs', PortalProgramController::class)->name('programs');
        Route::get('/programs/{trainingProgram}', PortalProgramDetailController::class)->name('programs.show');
        Route::post('/programs/{trainingProgram}/attendance/check-in', [PortalAttendanceCheckInController::class, 'checkInProgram'])
            ->name('programs.attendance.check-in');
        Route::get('/programs/{trainingProgram}/attendance/session', [PortalAttendanceSessionController::class, 'program'])
            ->name('programs.attendance.session');
        Route::get('/volunteering', PortalVolunteerController::class)->name('volunteering');
        Route::get('/certificates', PortalCertificateController::class)->name('certificates');

        Route::get('/profile', [PortalProfileController::class, 'show'])->name('profile');
        Route::patch('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/complete', [PortalProfileCompleteController::class, 'show'])->name('profile.complete');
        Route::post('/profile/complete', [PortalProfileCompleteController::class, 'store'])->name('profile.complete.store');

        Route::get('/settings', [PortalSettingsController::class, 'index'])->name('settings');
        Route::get('/settings/account', [PortalSettingsController::class, 'account'])->name('settings.account');
        Route::get('/settings/profile', [PortalSettingsController::class, 'profile'])->name('settings.profile');
        Route::get('/settings/legal', [PortalSettingsController::class, 'legal'])->name('settings.legal');
        Route::get('/settings/password', [PortalPasswordController::class, 'show'])->name('settings.password');
        Route::patch('/settings/password', [PortalPasswordController::class, 'update'])->name('settings.password.update');

        Route::get('/privacy-policy/acknowledge', [PortalPrivacyPolicyAcknowledgeController::class, 'show'])
            ->name('privacy-policy.acknowledge')
            ->withoutMiddleware('privacy.acknowledged');
        Route::post('/privacy-policy/acknowledge', [PortalPrivacyPolicyAcknowledgeController::class, 'store'])
            ->name('privacy-policy.acknowledge.store')
            ->withoutMiddleware('privacy.acknowledged');

        Route::get('/competency', [PortalCompetencyController::class, 'show'])->name('competency');
        Route::patch('/competency', [PortalCompetencyController::class, 'update'])->name('competency.update');
        Route::post('/competency/employment-consent', [\App\Http\Controllers\Portal\PortalCompetencyEmploymentConsentController::class, 'update'])
            ->name('competency.employment-consent');
        Route::get('/competency/export-pdf', PortalCompetencyExportController::class)->name('competency.export-pdf');

        Route::post('/competency/cv', [PortalCvDocumentController::class, 'store'])->name('competency.cv.store');
        Route::get('/competency/cv/download', [PortalCvDocumentController::class, 'download'])->name('competency.cv.download');
        Route::delete('/competency/cv', [PortalCvDocumentController::class, 'destroy'])->name('competency.cv.destroy');

        Route::post('/candidate-pool/prompted', [PortalCandidatePoolConsentController::class, 'prompted'])
            ->name('candidate-pool.prompted');
        Route::post('/candidate-pool/grant', [PortalCandidatePoolConsentController::class, 'grant'])
            ->name('candidate-pool.grant');
        Route::post('/candidate-pool/decline', [PortalCandidatePoolConsentController::class, 'decline'])
            ->name('candidate-pool.decline');
        Route::get('/candidate-pool/settings', [PortalCandidatePoolSettingsController::class, 'show'])
            ->name('candidate-pool.settings');
        Route::post('/candidate-pool/settings/grant', [PortalCandidatePoolSettingsController::class, 'grant'])
            ->name('candidate-pool.settings.grant');
        Route::post('/candidate-pool/settings/withdraw', [PortalCandidatePoolSettingsController::class, 'withdraw'])
            ->name('candidate-pool.settings.withdraw');

        Route::post('/account-deletion', [\App\Http\Controllers\Portal\PortalAccountDeletionController::class, 'store'])
            ->name('account-deletion.store');

    });

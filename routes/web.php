<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Portal\PortalCertificateController;
use App\Http\Controllers\Portal\PortalCompetencyController;
use App\Http\Controllers\Portal\PortalCompetencyExportController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalInboxController;
use App\Http\Controllers\Portal\PortalPathController;
use App\Http\Controllers\Portal\PortalPathDetailController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\PortalProgramController;
use App\Http\Controllers\Portal\PortalVolunteerController;
use App\Http\Controllers\Public\CertificateVerificationController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PublicLearningPathController;
use App\Http\Controllers\Public\PublicNewsController;
use App\Http\Controllers\Public\PublicTrainingProgramController;
use App\Http\Controllers\Public\PublicVolunteerOpportunityController;
use Illuminate\Support\Facades\Route;

// ─── Authentication ───────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    // Password Reset
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// ─── Public website ───────────────────────────────────────────────────────────

Route::get('/', HomeController::class)->name('home');
Route::view('/impact', 'public.impact')->name('impact.index');

// Certificate verification (public, no auth required)
Route::get('/certificates/verify/{code}', CertificateVerificationController::class)->name('certificates.verify');

Route::prefix('paths')->name('public.paths.')->group(function () {
    Route::get('/', [PublicLearningPathController::class, 'index'])->name('index');
    Route::get('/{learningPath:slug}', [PublicLearningPathController::class, 'show'])->name('show');
    Route::post('/{learningPath:slug}/register', [PublicLearningPathController::class, 'register'])->middleware('auth')->name('register');
});

Route::prefix('programs')->name('public.programs.')->group(function () {
    Route::get('/', [PublicTrainingProgramController::class, 'index'])->name('index');
    Route::get('/{trainingProgram:slug}', [PublicTrainingProgramController::class, 'show'])->name('show');
    Route::post('/{trainingProgram:slug}/register', [PublicTrainingProgramController::class, 'register'])->middleware('auth')->name('register');
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

// ─── Beneficiary Portal ───────────────────────────────────────────────────────

Route::middleware(['auth', 'beneficiary'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {

        Route::get('/', PortalDashboardController::class)->name('dashboard');

        Route::get('/notifications', [PortalInboxController::class, 'index'])->name('notifications');
        Route::post('/notifications/read-all', [PortalInboxController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [PortalInboxController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/{notification}/registration-action', [PortalInboxController::class, 'registrationAction'])
            ->name('notifications.registration-action');
        Route::get('/paths', PortalPathController::class)->name('paths');
        Route::get('/paths/{learningPath}', PortalPathDetailController::class)->name('paths.show');
        Route::get('/programs', PortalProgramController::class)->name('programs');
        Route::get('/volunteering', PortalVolunteerController::class)->name('volunteering');
        Route::get('/certificates', PortalCertificateController::class)->name('certificates');

        Route::get('/profile', [PortalProfileController::class, 'show'])->name('profile');
        Route::patch('/profile', [PortalProfileController::class, 'update'])->name('profile.update');

        Route::get('/competency', [PortalCompetencyController::class, 'show'])->name('competency');
        Route::patch('/competency', [PortalCompetencyController::class, 'update'])->name('competency.update');
        Route::get('/competency/export-pdf', PortalCompetencyExportController::class)->name('competency.export-pdf');

    });

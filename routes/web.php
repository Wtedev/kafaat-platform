<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Public\CertificateVerificationController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PublicLearningPathController;
use App\Http\Controllers\Public\PublicTrainingProgramController;
use App\Http\Controllers\Public\PublicVolunteerOpportunityController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalPathController;
use App\Http\Controllers\Portal\PortalProgramController;
use App\Http\Controllers\Portal\PortalVolunteerController;
use App\Http\Controllers\Portal\PortalCertificateController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\Portal\PortalCourseController;
use Illuminate\Support\Facades\Route;

// ─── Authentication ───────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login',    [LoginController::class, 'show'])->name('login');
    Route::post('/login',   [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    // Password Reset
    Route::get('/forgot-password',        [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password',       [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password',        [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

// ─── Public website ───────────────────────────────────────────────────────────

Route::get('/', HomeController::class)->name('home');

// Certificate verification (public, no auth required)
Route::get('/certificates/verify/{code}', CertificateVerificationController::class)->name('certificates.verify');

Route::prefix('paths')->name('public.paths.')->group(function () {
    Route::get('/',                              [PublicLearningPathController::class, 'index'])->name('index');
    Route::get('/{learningPath:slug}',           [PublicLearningPathController::class, 'show'])->name('show');
    Route::post('/{learningPath:slug}/register', [PublicLearningPathController::class, 'register'])->middleware('auth')->name('register');
});

Route::prefix('programs')->name('public.programs.')->group(function () {
    Route::get('/',                               [PublicTrainingProgramController::class, 'index'])->name('index');
    Route::get('/{trainingProgram:slug}',         [PublicTrainingProgramController::class, 'show'])->name('show');
    Route::post('/{trainingProgram:slug}/register', [PublicTrainingProgramController::class, 'register'])->middleware('auth')->name('register');
});

Route::prefix('volunteering')->name('public.volunteering.')->group(function () {
    Route::get('/',                                    [PublicVolunteerOpportunityController::class, 'index'])->name('index');
    Route::get('/{volunteerOpportunity:slug}',         [PublicVolunteerOpportunityController::class, 'show'])->name('show');
    Route::post('/{volunteerOpportunity:slug}/register', [PublicVolunteerOpportunityController::class, 'register'])->middleware('auth')->name('register');
});

// ─── Beneficiary Portal ───────────────────────────────────────────────────────

Route::middleware(['auth', 'beneficiary'])
    ->prefix('portal')
    ->name('portal.')
    ->group(function () {

        Route::get('/',             PortalDashboardController::class)->name('dashboard');
        Route::get('/paths',        PortalPathController::class)->name('paths');
        Route::get('/programs',     PortalProgramController::class)->name('programs');
        Route::get('/volunteering', PortalVolunteerController::class)->name('volunteering');
        Route::get('/certificates', PortalCertificateController::class)->name('certificates');

        Route::get('/profile',   [PortalProfileController::class, 'show'])->name('profile');
        Route::patch('/profile', [PortalProfileController::class, 'update'])->name('profile.update');

        // Learning path courses
        Route::get('/paths/{learningPath}/courses',              [PortalCourseController::class, 'index'])->name('paths.courses');
        Route::get('/paths/{learningPath}/courses/{pathCourse}', [PortalCourseController::class, 'show'])->name('paths.courses.show');
        Route::post('/courses/{pathCourse}/start',               [PortalCourseController::class, 'start'])->name('courses.start');
        Route::post('/courses/{pathCourse}/progress',            [PortalCourseController::class, 'progress'])->name('courses.progress');
        Route::post('/courses/{pathCourse}/complete',            [PortalCourseController::class, 'complete'])->name('courses.complete');
    });


use Illuminate\Support\Facades\Artisan;
use App\Models\User;

Route::get('/fix-production-access-SECRET123', function () {
    Artisan::call('migrate', ['--force' => true]);
    Artisan::call('db:seed', [
        '--class' => 'RolesAndPermissionsSeeder',
        '--force' => true,
    ]);
    Artisan::call('permission:cache-reset');

    $user = User::where('email', 'ايميلك هنا')->firstOrFail();

    $user->forceFill([
        'role_type' => 'admin',
        'is_active' => true,
    ])->save();

    $user->syncRoles(['admin']);

    return 'DONE: user is now admin and active';
});
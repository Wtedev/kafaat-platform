<?php

/**
 * Usage: php docs/audits/screenshots/gate-live-session/render-fixture.php
 */

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Models\ProgramAttendance;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\AttendanceLiveSessionService;
use App\Services\ProgramAttendanceCheckerAccessService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../../../vendor/autoload.php';

$sqlite = __DIR__.'/../../../../database/screenshot-gate-live-session.sqlite';
@unlink($sqlite);
touch($sqlite);

$env = [
    'APP_ENV' => 'local',
    'APP_KEY' => 'base64:2fl+KtvkphFvK63E2vNWEgM0H2lGzRz1KwX8ZJ5aF0E=',
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://127.0.0.1:8767',
    'APP_TIMEZONE' => 'Asia/Riyadh',
    'APP_LOCALE' => 'ar',
    'APP_FALLBACK_LOCALE' => 'ar',
    'FORCE_HTTPS' => 'false',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $sqlite,
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'IDENTITY_LOOKUP_KEY' => 'base64:dGVzdC1pZGVudGl0eS1sb29rdXAta2V5LTMyYnl0ZXM=',
    'PRIVATE_DOCUMENTS_DISK' => 'private_documents',
    'CV_MAX_SIZE_KB' => '10240',
];
foreach ($env as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.sqlite.database' => $sqlite]);
Carbon::setTestNow(Carbon::parse('2026-08-12 10:00:00', 'Asia/Riyadh'));
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
View::composer('*', function ($view): void {
    if (! $view->offsetExists('errors')) {
        $view->with('errors', new ViewErrorBag);
    }
});

$program = TrainingProgram::query()->create([
    'title' => 'قادة التطوع — جلسة التحضير',
    'slug' => 'gate-live-shot',
    'status' => ProgramStatus::Published,
    'published_at' => now(),
    'program_kind' => TrainingProgramKind::Course,
    'delivery_mode' => ProgramDeliveryMode::Hybrid,
    'venue' => 'قاعة كفاءات',
]);
ProgramPrepDay::query()->create([
    'training_program_id' => $program->id,
    'prep_date' => '2026-08-12',
    'delivery_type' => ProgramPrepDayType::Remote,
    'requires_attendance' => true,
]);
$access = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'لمى فؤاد');
/** @var ProgramAttendanceChecker $checker */
$checker = $access['checker'];

$users = [];
foreach ([['سارة', 's1@example.test'], ['نورة', 's2@example.test'], ['مها', 's3@example.test']] as [$name, $email]) {
    $user = User::factory()->create([
        'email' => $email,
        'first_name' => $name,
        'father_name' => 'أحمد',
        'grandfather_name' => 'علي',
        'family_name' => 'العلي',
        'role_type' => 'beneficiary',
        'is_active' => true,
        'email_verified_at' => now(),
        'notification_prefs_set_at' => now(),
    ]);
    $user->assignRole('beneficiary');
    ProgramRegistration::query()->create([
        'user_id' => $user->id,
        'training_program_id' => $program->id,
        'status' => RegistrationStatus::Approved,
        'approved_at' => now()->subDay(),
    ]);
    $users[] = $user;
}

$manifest = json_decode((string) file_get_contents(__DIR__.'/../../../../public/build/manifest.json'), true);
$cssRel = $manifest['resources/css/app.css']['file'] ?? 'assets/app.css';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$live = $app->make(AttendanceLiveSessionService::class);
$outDir = __DIR__;

$render = static function (string $name) use ($kernel, $program, $checker, $cssRel, $outDir): void {
    $request = Request::create('/gate/'.$program->slug.'/portal?tab=manual', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put(EnsureGateAttendanceAccess::SESSION_CHECKER_ID, $checker->id);
    $request->session()->put(EnsureGateAttendanceAccess::SESSION_PROGRAM_ID, $program->id);
    $request->session()->put(EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION, (int) $checker->access_version);
    $response = $kernel->handle($request);
    $html = $response->getContent();
    $kernel->terminate($request, $response);
    $html = preg_replace('~<link[^>]+href="[^"]*build/[^"]+"[^>]*>~', '', $html) ?? $html;
    $html = preg_replace('~<script[^>]+src="[^"]*build/[^"]+"[^>]*>\s*</script>~', '', $html) ?? $html;
    $html = preg_replace(
        '~</head>~',
        '<link rel="stylesheet" href="/build/'.htmlspecialchars($cssRel, ENT_QUOTES, 'UTF-8').'">'."\n</head>",
        $html,
        1,
    ) ?? $html;
    file_put_contents($outDir.'/'.$name.'.html', $html);
};

$render('01-before-open');

$session = $live->startProgramRemoteSession($program, $checker);
Carbon::setTestNow(Carbon::parse('2026-08-12 10:01:10', 'Asia/Riyadh'));
$render('02-countdown');

$reg = ProgramRegistration::query()->where('user_id', $users[0]->id)->first();
ProgramAttendance::query()->create([
    'program_registration_id' => $reg->id,
    'training_date' => '2026-08-12',
    'status' => AttendanceStatus::Present,
    'notes' => 'تسجيل حضور ذاتي',
]);
$render('03-after-checkin');

$live->endSession($session->fresh());
Carbon::setTestNow(Carbon::parse('2026-08-12 10:03:00', 'Asia/Riyadh'));
$render('04-after-end');

fwrite(STDOUT, "Rendered gate live-session fixtures\n");

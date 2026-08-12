<?php

/**
 * Local fixture renderer for beneficiary program-detail screenshots.
 * Usage: php docs/audits/screenshots/portal-program-details/render-fixture.php
 */

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\AttendanceLiveSession;
use App\Models\ProgramAttendance;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\Portal\PortalProgramDetailPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../../../vendor/autoload.php';

$sqlite = __DIR__.'/../../../../database/screenshot-portal-program-detail.sqlite';
@unlink($sqlite);
touch($sqlite);

$env = [
    'APP_ENV' => 'local',
    'APP_KEY' => 'base64:2fl+KtvkphFvK63E2vNWEgM0H2lGzRz1KwX8ZJ5aF0E=',
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://127.0.0.1:8766',
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

$outDir = __DIR__;

$manifest = json_decode((string) file_get_contents(__DIR__.'/../../../../public/build/manifest.json'), true);
$cssRel = $manifest['resources/css/app.css']['file'] ?? 'assets/app.css';

$makeUser = static function (string $email): User {
    $user = User::factory()->create([
        'email' => $email,
        'first_name' => 'لمى',
        'father_name' => 'فؤاد',
        'grandfather_name' => 'سليمان',
        'family_name' => 'المشيقح',
        'role_type' => 'beneficiary',
        'is_active' => true,
        'email_verified_at' => now(),
        'notification_prefs_set_at' => now(),
    ]);
    $user->assignRole('beneficiary');

    return $user;
};

$makeProgram = static function (
    string $title,
    string $slug,
    ProgramDeliveryMode $mode,
    ?string $start,
    ?string $end,
): TrainingProgram {
    return TrainingProgram::query()->create([
        'title' => $title,
        'slug' => $slug,
        'status' => ProgramStatus::Published,
        'published_at' => now()->subDay(),
        'delivery_mode' => $mode,
        'venue' => $mode->hasPhysicalComponent() ? 'قاعة كفاءات — الرياض' : null,
        'start_date' => $start,
        'end_date' => $end,
        'whatsapp_groups_enabled' => true,
        'whatsapp_group_female' => 'https://chat.whatsapp.com/demo',
    ]);
};

$register = static function (User $user, TrainingProgram $program, RegistrationStatus $status = RegistrationStatus::Approved): ProgramRegistration {
    return ProgramRegistration::query()->create([
        'user_id' => $user->id,
        'training_program_id' => $program->id,
        'status' => $status,
        'approved_at' => $status === RegistrationStatus::Approved || $status === RegistrationStatus::Completed ? now()->subDays(5) : null,
        'score' => $status === RegistrationStatus::Completed ? 88 : null,
    ]);
};

$addDay = static function (TrainingProgram $program, string $date, ProgramPrepDayType $type): void {
    ProgramPrepDay::query()->create([
        'training_program_id' => $program->id,
        'prep_date' => $date,
        'delivery_type' => $type,
        'requires_attendance' => true,
    ]);
};

$scenarios = [];

$inPersonUser = $makeUser('shot.inperson@example.test');
$inPerson = $makeProgram('قادة التطوع — حضوري', 'shot-inperson', ProgramDeliveryMode::InPerson, '2026-08-03', '2026-08-18');
$inPersonReg = $register($inPersonUser, $inPerson);
$addDay($inPerson, '2026-08-10', ProgramPrepDayType::InPerson);
$addDay($inPerson, '2026-08-12', ProgramPrepDayType::InPerson);
$addDay($inPerson, '2026-08-16', ProgramPrepDayType::InPerson);
ProgramAttendance::query()->create([
    'program_registration_id' => $inPersonReg->id,
    'training_date' => '2026-08-10',
    'status' => AttendanceStatus::Present,
    'notes' => 'تحضير بوابة QR | اليوم: 2026-08-10',
]);
$scenarios['inperson-running'] = [$inPersonUser, $inPersonReg];

$remoteUser = $makeUser('shot.remote@example.test');
$remote = $makeProgram('قادة التطوع — عن بُعد', 'shot-remote', ProgramDeliveryMode::Remote, '2026-08-03', '2026-08-18');
$remoteReg = $register($remoteUser, $remote);
$addDay($remote, '2026-08-10', ProgramPrepDayType::Remote);
$addDay($remote, '2026-08-12', ProgramPrepDayType::Remote);
$addDay($remote, '2026-08-18', ProgramPrepDayType::Remote);
ProgramAttendance::query()->create([
    'program_registration_id' => $remoteReg->id,
    'training_date' => '2026-08-10',
    'status' => AttendanceStatus::Present,
    'notes' => 'تسجيل حضور ذاتي',
]);
AttendanceLiveSession::query()->create([
    'attendable_type' => $remote->getMorphClass(),
    'attendable_id' => $remote->id,
    'session_date' => '2026-08-12',
    'created_by' => $remoteUser->id,
    'started_at' => now(),
    'expires_at' => now()->addMinutes(5),
]);
$scenarios['remote-running'] = [$remoteUser, $remoteReg];

$hybridUser = $makeUser('shot.hybrid@example.test');
$hybrid = $makeProgram('قادة التطوع — مدمج', 'shot-hybrid', ProgramDeliveryMode::Hybrid, '2026-08-03', '2026-08-18');
$hybridReg = $register($hybridUser, $hybrid);
$addDay($hybrid, '2026-08-10', ProgramPrepDayType::InPerson);
$addDay($hybrid, '2026-08-12', ProgramPrepDayType::Remote);
$addDay($hybrid, '2026-08-16', ProgramPrepDayType::InPerson);
ProgramAttendance::query()->create([
    'program_registration_id' => $hybridReg->id,
    'training_date' => '2026-08-10',
    'status' => AttendanceStatus::Present,
    'notes' => 'تحضير يدوي — مسؤول #1',
]);
$scenarios['hybrid-running'] = [$hybridUser, $hybridReg];

$upcomingUser = $makeUser('shot.upcoming@example.test');
$upcoming = $makeProgram('ملتقى المهارات — لم يبدأ', 'shot-upcoming', ProgramDeliveryMode::InPerson, '2026-08-20', '2026-08-22');
$upcomingReg = $register($upcomingUser, $upcoming);
$addDay($upcoming, '2026-08-20', ProgramPrepDayType::InPerson);
$addDay($upcoming, '2026-08-21', ProgramPrepDayType::InPerson);
$scenarios['not-started'] = [$upcomingUser, $upcomingReg];

$doneUser = $makeUser('shot.done@example.test');
$done = $makeProgram('ورشة القياس — مكتمل', 'shot-done', ProgramDeliveryMode::Remote, '2026-08-03', '2026-08-05');
$doneReg = $register($doneUser, $done, RegistrationStatus::Completed);
$doneReg->update(['score' => 90]);
$addDay($done, '2026-08-03', ProgramPrepDayType::Remote);
$addDay($done, '2026-08-04', ProgramPrepDayType::Remote);
$addDay($done, '2026-08-05', ProgramPrepDayType::Remote);
foreach (['2026-08-03', '2026-08-04', '2026-08-05'] as $date) {
    ProgramAttendance::query()->create([
        'program_registration_id' => $doneReg->id,
        'training_date' => $date,
        'status' => AttendanceStatus::Present,
        'notes' => 'تسجيل حضور ذاتي',
    ]);
}
$scenarios['completed'] = [$doneUser, $doneReg];

$presenter = $app->make(PortalProgramDetailPresenter::class);
View::composer('*', function ($view): void {
    if (! $view->offsetExists('errors')) {
        $view->with('errors', new ViewErrorBag);
    }
});
$files = [];

foreach ($scenarios as $name => [$user, $registration]) {
    $registration->refresh()->load(['trainingProgram.prepDays', 'attendanceRecords']);
    Auth::login($user);
    $html = view('portal.program-show', $presenter->present($registration, $user))->render();
    $html = preg_replace('~<link[^>]+href="[^"]*build/[^"]+"[^>]*>~', '', $html) ?? $html;
    $html = preg_replace('~<script[^>]+src="[^"]*build/[^"]+"[^>]*>\s*</script>~', '', $html) ?? $html;
    $html = preg_replace(
        '~</head>~',
        '<link rel="stylesheet" href="/build/'.htmlspecialchars($cssRel, ENT_QUOTES, 'UTF-8').'">'."\n</head>",
        $html,
        1,
    ) ?? $html;

    $file = $name.'.html';
    file_put_contents($outDir.'/'.$file, $html);
    $files[$name] = '/'.$file;
}

file_put_contents($outDir.'/fixture-meta.json', json_encode([
    'files' => $files,
    'generated_at' => now()->toIso8601String(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

fwrite(STDOUT, 'Rendered '.count($files)." fixtures → {$outDir}\n");

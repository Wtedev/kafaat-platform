<?php

/**
 * Local fixture renderer for prep portal screenshots.
 * Usage: php docs/audits/screenshots/prep-attendance-portal-tabs/render-fixture.php
 */

declare(strict_types=1);

use App\Enums\CompetencyTrack;
use App\Enums\ProgramDeliveryMode;
use App\Enums\ProgramPrepDayType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Models\ProgramAttendanceChecker;
use App\Models\ProgramPrepDay;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramAttendanceCheckerAccessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../../../vendor/autoload.php';

$sqlite = __DIR__.'/../../../../database/screenshot-prep-portal.sqlite';
@unlink($sqlite);
touch($sqlite);

$env = [
    'APP_ENV' => 'local',
    'APP_KEY' => 'base64:2fl+KtvkphFvK63E2vNWEgM0H2lGzRz1KwX8ZJ5aF0E=',
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://127.0.0.1:8765',
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

$program = TrainingProgram::query()->create([
    'title' => 'قادة التطوع — مراجعة واجهة التحضير',
    'slug' => 'prep-portal-screenshot-'.uniqid(),
    'description' => 'وصف',
    'program_kind' => TrainingProgramKind::Course,
    'competency_track' => CompetencyTrack::Self,
    'delivery_mode' => ProgramDeliveryMode::Remote,
    'venue' => 'عن بُعد',
    'status' => ProgramStatus::Published,
    'published_at' => now()->subDay(),
    'capacity' => 200,
    'auto_accept_registrations' => true,
]);

ProgramPrepDay::query()->create([
    'training_program_id' => $program->id,
    'prep_date' => '2026-08-12',
    'delivery_type' => ProgramPrepDayType::Remote,
    'requires_attendance' => true,
]);

$access = app(ProgramAttendanceCheckerAccessService::class)->create($program, 'لمى فؤاد سليمان المشيقح');
/** @var ProgramAttendanceChecker $checker */
$checker = $access['checker'];

$longNames = [
    ['عبدالرحمن', 'سليمان', 'عبدالله', 'آل الشيخ الطويل جداً للتحقق من الالتفاف'],
    ['نورة', 'سعد', 'فهد', 'القحطاني العتيبي الزاهر'],
    ['محمد', 'عبدالعزيز', 'إبراهيم', 'الخالدي بن عبدالرحمن'],
];

for ($i = 1; $i <= 180; $i++) {
    $parts = $longNames[($i - 1) % count($longNames)];
    $user = User::factory()->create([
        'name' => "legacy-{$i}",
        'first_name' => $parts[0],
        'father_name' => $parts[1],
        'grandfather_name' => $parts[2],
        'family_name' => $parts[3].' '.$i,
        'email' => sprintf('prep.shot.%03d@example.test', $i),
    ]);

    ProgramRegistration::query()->create([
        'training_program_id' => $program->id,
        'user_id' => $user->id,
        'status' => RegistrationStatus::Approved,
        'approved_at' => now(),
    ]);
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create(
    '/gate/'.$program->slug.'/portal?tab=manual',
    'GET',
);
$request->setLaravelSession($app['session']->driver());
$request->session()->put(EnsureGateAttendanceAccess::SESSION_CHECKER_ID, $checker->id);
$request->session()->put(EnsureGateAttendanceAccess::SESSION_PROGRAM_ID, $program->id);
$request->session()->put(EnsureGateAttendanceAccess::SESSION_ACCESS_VERSION, (int) $checker->access_version);

$response = $kernel->handle($request);
$html = $response->getContent();
$kernel->terminate($request, $response);

$manifest = json_decode((string) file_get_contents(__DIR__.'/../../../../public/build/manifest.json'), true);
$cssRel = $manifest['resources/css/app.css']['file'] ?? 'assets/app.css';
$cssPath = realpath(__DIR__.'/../../../../public/build/'.$cssRel);
$cssHref = 'file://'.$cssPath;

$html = preg_replace('~<link[^>]+href="[^"]*build/[^"]+"[^>]*>~', '', $html) ?? $html;
$html = preg_replace('~<script[^>]+src="[^"]*build/[^"]+"[^>]*>\s*</script>~', '', $html) ?? $html;
$html = preg_replace(
    '~</head>~',
    '<link rel="stylesheet" href="'.htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8').'">'."\n</head>",
    $html,
    1
) ?? $html;

$outDir = __DIR__;
file_put_contents($outDir.'/manual-table-live.html', $html);

$meta = [
    'program_slug' => $program->slug,
    'registrations' => 180,
    'pages' => 9,
    'generated_at' => now()->toIso8601String(),
];
file_put_contents($outDir.'/fixture-meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

fwrite(STDOUT, "Rendered fixture → {$outDir}/manual-table-live.html\n");
fwrite(STDOUT, "HTTP status: {$response->getStatusCode()}\n");
fwrite(STDOUT, 'HTML bytes: '.strlen($html)."\n");

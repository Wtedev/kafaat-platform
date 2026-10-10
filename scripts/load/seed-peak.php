<?php

use App\Enums\IdentityType;
use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Attendance\ProgramAttendanceLinkService;
use App\Services\Identity\IdentityNumberService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$url = (string) config('app.url');
if (str_contains($url, 'kafaat.org.sa')) {
    fwrite(STDERR, "refusing to seed a database whose APP_URL is production\n");
    exit(1);
}

if (User::query()->count() > 100) {
    fwrite(STDERR, "refusing to seed a database that already has users\n");
    exit(1);
}

$beneficiaries = 1800;
$approved = 1000;
$logins = 225;

$program = TrainingProgram::query()->create([
    'title' => 'برنامج اختبار الحمل',
    'slug' => 'load-peak',
    'status' => ProgramStatus::Published,
    'published_at' => now(),
    'start_date' => now()->toDateString(),
    'end_date' => now()->addDays(2)->toDateString(),
    'registration_start' => now()->subDay()->toDateString(),
    'registration_end' => now()->addMonth()->toDateString(),
]);

$link = app(ProgramAttendanceLinkService::class)->create($program, 'اختبار الحمل', 60);
app(ProgramAttendanceLinkService::class)->open($link);

$identities = [];
$loginRows = [];

for ($i = 1; $i <= $beneficiaries; $i++) {
    $identity = sprintf('1%09d', $i);
    $email = 'load-peak-'.$i.'@example.test';
    $user = User::factory()->create([
        'name' => 'مشارك '.$i,
        'first_name' => 'نو',
        'family_name' => 'سعد',
        'email' => $email,
        'role_type' => 'beneficiary',
    ]);
    $user->forceFill(IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId))->save();

    if ($i <= $approved) {
        ProgramRegistration::query()->create([
            'training_program_id' => $program->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Approved,
        ]);
        $identities[] = $identity;
    }

    if ($i <= $logins) {
        $loginRows[] = ['email' => $email, 'password' => 'password'];
    }
}

file_put_contents(__DIR__.'/identities.json', json_encode($identities, JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__.'/logins.json', json_encode($loginRows, JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__.'/peak-target.json', json_encode([
    'base' => rtrim($url, '/'),
    'program_path' => '/programs/load-peak',
    'attendance_url' => $link->fresh()->publicUrl(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo 'users='.$beneficiaries.PHP_EOL;
echo 'approved='.$approved.PHP_EOL;
echo 'program=/programs/load-peak'.PHP_EOL;
echo 'attendance='.$link->fresh()->publicUrl().PHP_EOL;

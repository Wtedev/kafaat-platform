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

$count = (int) ($argv[1] ?? 1500);
$program = TrainingProgram::query()->create([
    'title' => 'ملتقى تحليل البيانات',
    'slug' => 'load-attendance-'.uniqid(),
    'status' => ProgramStatus::Published,
    'published_at' => now(),
    'start_date' => now()->toDateString(),
    'end_date' => now()->addDays(2)->toDateString(),
    'registration_start' => now()->subDay()->toDateString(),
    'registration_end' => now()->addMonth()->toDateString(),
]);

$link = app(ProgramAttendanceLinkService::class)->create($program, 'اليوم الأول', 30);
app(ProgramAttendanceLinkService::class)->open($link);

$identities = [];

for ($i = 1; $i <= $count; $i++) {
    $identity = sprintf('1%09d', $i);
    $user = User::factory()->create([
        'name' => 'مشارك '.$i,
        'first_name' => 'نو',
        'family_name' => 'سعد',
        'email' => 'load-attendance-'.$i.'@example.test',
        'role_type' => 'beneficiary',
    ]);
    $user->forceFill(IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId))->save();
    ProgramRegistration::query()->create([
        'training_program_id' => $program->id,
        'user_id' => $user->id,
        'status' => RegistrationStatus::Approved,
    ]);
    $identities[] = $identity;
}

file_put_contents(__DIR__.'/identities.json', json_encode($identities, JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__.'/target.txt', $link->fresh()->publicUrl());

echo 'url='.$link->fresh()->publicUrl().PHP_EOL;
echo 'identities='.$count.PHP_EOL;
echo 'demo_identity='.$identities[0].PHP_EOL;

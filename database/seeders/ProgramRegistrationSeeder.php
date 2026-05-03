<?php

namespace Database\Seeders;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Services\ProgramRegistrationService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Distributes program/path/volunteer registrations across seeded beneficiaries.
 * Issues certificates only via {@see ProgramRegistrationService::markCompleted()} for eligible rows.
 */
class ProgramRegistrationSeeder extends Seeder
{
    public function run(): void
    {
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        $admin = User::query()->where('email', 'lama@kafaat.org.sa')->first()
            ?? User::query()->where('email', 'abdulsalam@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        if ($admin === null) {
            $this->command?->error('ProgramRegistrationSeeder: no admin user found.');

            return;
        }

        $beneficiaries = User::query()
            ->where('email', 'like', 'beneficiary.%@seed.kafaat.org.sa')
            ->orderBy('email')
            ->get();

        if ($beneficiaries->count() < 150) {
            $this->command?->error('ProgramRegistrationSeeder: expected 150 beneficiaries; found '.$beneficiaries->count().'. Run BeneficiaryUserSeeder first.');

            return;
        }

        $programs = TrainingProgram::query()
            ->where('status', ProgramStatus::Published)
            ->orderBy('id')
            ->get();

        if ($programs->isEmpty()) {
            $this->command?->error('ProgramRegistrationSeeder: no published programs found.');

            return;
        }

        $paths = LearningPath::query()->orderBy('id')->get();

        $this->seedPathRegistrations($beneficiaries, $paths, $admin);
        $this->seedProgramRegistrations($beneficiaries, $programs, $admin);
        $this->seedVolunteerDomain($beneficiaries, $admin);

        $this->command?->info('  ProgramRegistrationSeeder: path, program, and volunteer rows updated.');
    }

    /**
     * @param  Collection<int, User>  $beneficiaries
     * @param  Collection<int, LearningPath>  $paths
     */
    private function seedPathRegistrations($beneficiaries, $paths, User $admin): void
    {
        if ($paths->isEmpty()) {
            return;
        }

        PathRegistration::withoutEvents(function () use ($beneficiaries, $paths, $admin): void {
            foreach ($beneficiaries->values() as $idx => $user) {
                if ($idx % 2 !== 0) {
                    continue;
                }
                $path = $paths[$idx % $paths->count()];
                $statusRoll = ($idx * 5) % 100;

                $status = match (true) {
                    $statusRoll < 12 => RegistrationStatus::Pending,
                    $statusRoll < 22 => RegistrationStatus::Rejected,
                    $statusRoll < 78 => RegistrationStatus::Approved,
                    default => RegistrationStatus::Completed,
                };

                $approved = in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected], true);

                PathRegistration::updateOrCreate(
                    ['learning_path_id' => $path->id, 'user_id' => $user->id],
                    [
                        'status' => $status,
                        'rejected_reason' => $status === RegistrationStatus::Rejected ? 'عدم استيفاء شرط المسار.' : null,
                        'approved_by' => $approved ? $admin->id : null,
                        'approved_at' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
                            ? Carbon::now()->subDays(10 + ($idx % 20))
                            : null,
                        'completed_at' => $status === RegistrationStatus::Completed
                            ? Carbon::now()->subDays(2 + ($idx % 8))
                            : null,
                    ]
                );
            }
        });
    }

    /**
     * @param  Collection<int, User>  $beneficiaries
     * @param  Collection<int, TrainingProgram>  $programs
     */
    private function seedProgramRegistrations($beneficiaries, $programs, User $admin): void
    {
        $seen = [];
        $markCompletedQueue = [];

        ProgramRegistration::withoutEvents(function () use ($beneficiaries, $programs, $admin, &$seen, &$markCompletedQueue): void {
            foreach ($beneficiaries->values() as $uIdx => $user) {
                for ($slot = 0; $slot < 3; $slot++) {
                    $program = $programs[($uIdx * 5 + $slot * 13) % $programs->count()];
                    $key = $user->id.'-'.$program->id;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $roll = ($uIdx * 7 + $slot * 11) % 100;

                    if ($roll < 14) {
                        $this->upsertProgramRegistration($program->id, $user->id, RegistrationStatus::Pending, $admin, $uIdx, $slot, null, null);

                        continue;
                    }

                    if ($roll < 23) {
                        $this->upsertProgramRegistration($program->id, $user->id, RegistrationStatus::Rejected, $admin, $uIdx, $slot, null, null);

                        continue;
                    }

                    if ($roll < 31) {
                        $this->upsertProgramRegistration($program->id, $user->id, RegistrationStatus::Cancelled, $admin, $uIdx, $slot, null, null);

                        continue;
                    }

                    if ($roll < 56) {
                        $this->upsertProgramRegistration($program->id, $user->id, RegistrationStatus::Approved, $admin, $uIdx, $slot, null, null);

                        continue;
                    }

                    if ($roll < 72) {
                        $this->upsertProgramRegistration(
                            $program->id,
                            $user->id,
                            RegistrationStatus::Completed,
                            $admin,
                            $uIdx,
                            $slot,
                            (float) (45 + (($uIdx + $slot) % 30)),
                            (float) (35 + (($uIdx * 2 + $slot) % 20)),
                        );

                        continue;
                    }

                    if ($roll < 94) {
                        $this->upsertProgramRegistration($program->id, $user->id, RegistrationStatus::Approved, $admin, $uIdx, $slot, null, null);

                        continue;
                    }

                    $this->upsertProgramRegistration($program->id, $user->id, RegistrationStatus::Approved, $admin, $uIdx, $slot, null, null);
                    $reg = ProgramRegistration::query()
                        ->where('training_program_id', $program->id)
                        ->where('user_id', $user->id)
                        ->first();
                    if ($reg !== null) {
                        $markCompletedQueue[] = [
                            'id' => $reg->id,
                            'score' => (float) (65 + (($uIdx * 3 + $slot) % 36)),
                            'att' => (float) (82 + (($uIdx + $slot * 2) % 19)),
                        ];
                    }
                }
            }
        });

        $service = app(ProgramRegistrationService::class);
        foreach ($markCompletedQueue as $i => $item) {
            $reg = ProgramRegistration::query()->find($item['id']);
            if ($reg === null || ! $reg->isApproved()) {
                continue;
            }
            try {
                $service->markCompleted($reg, $admin, $item['score'], $item['att']);
            } catch (\Throwable $e) {
                $this->command?->warn('ProgramRegistrationSeeder: markCompleted failed for registration '.$reg->id.': '.$e->getMessage());
            }
            if (($i + 1) % 5 === 0) {
                gc_collect_cycles();
            }
        }
    }

    private function upsertProgramRegistration(
        int $programId,
        int $userId,
        RegistrationStatus $status,
        User $admin,
        int $uIdx,
        int $slot,
        ?float $attendance,
        ?float $score,
    ): void {
        $approvedBy = in_array($status, [
            RegistrationStatus::Rejected,
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true) ? $admin->id : null;

        $approvedAt = in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
            ? Carbon::now()->subDays(5 + (($uIdx + $slot) % 25))
            : null;

        ProgramRegistration::updateOrCreate(
            ['training_program_id' => $programId, 'user_id' => $userId],
            [
                'status' => $status,
                'rejected_reason' => $status === RegistrationStatus::Rejected ? 'عدم اكتمال المتطلبات.' : null,
                'approved_by' => $approvedBy,
                'approved_at' => $approvedAt,
                'attendance_percentage' => $attendance ?? 0.0,
                'score' => $score,
            ]
        );
    }

    /**
     * @param  Collection<int, User>  $beneficiaries
     */
    private function seedVolunteerDomain($beneficiaries, User $admin): void
    {
        $opportunities = VolunteerOpportunity::query()->orderBy('id')->get();
        if ($opportunities->isEmpty()) {
            return;
        }

        $volunteers = $beneficiaries->filter(fn (User $u) => $u->hasRole('volunteer'))->values();
        if ($volunteers->isEmpty()) {
            return;
        }

        VolunteerRegistration::withoutEvents(function () use ($volunteers, $opportunities, $admin): void {
            foreach ($volunteers as $i => $user) {
                $opp = $opportunities[$i % $opportunities->count()];
                $roll = ($i * 9) % 100;
                $status = match (true) {
                    $roll < 15 => RegistrationStatus::Pending,
                    $roll < 25 => RegistrationStatus::Rejected,
                    $roll < 40 => RegistrationStatus::Approved,
                    default => RegistrationStatus::Completed,
                };

                $approved = in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed, RegistrationStatus::Rejected], true);

                VolunteerRegistration::updateOrCreate(
                    ['opportunity_id' => $opp->id, 'user_id' => $user->id],
                    [
                        'status' => $status,
                        'rejected_reason' => $status === RegistrationStatus::Rejected ? 'عدم توفر المدة المطلوبة.' : null,
                        'approved_by' => $approved ? $admin->id : null,
                        'approved_at' => in_array($status, [RegistrationStatus::Approved, RegistrationStatus::Completed], true)
                            ? Carbon::now()->subDays(6 + ($i % 18))
                            : null,
                    ]
                );
            }
        });

        VolunteerHour::withoutEvents(function () use ($volunteers, $opportunities, $admin): void {
            foreach ($volunteers as $i => $user) {
                if ($i % 3 !== 0) {
                    continue;
                }
                $opp = $opportunities[$i % $opportunities->count()];
                $reg = VolunteerRegistration::query()
                    ->where('opportunity_id', $opp->id)
                    ->where('user_id', $user->id)
                    ->first();
                if ($reg === null || $reg->status !== RegistrationStatus::Completed) {
                    continue;
                }

                VolunteerHour::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'opportunity_id' => $opp->id,
                    ],
                    [
                        'hours' => (float) (4 + ($i % 12)),
                        'status' => VolunteerHoursStatus::Approved,
                        'approved_by' => $admin->id,
                        'approved_at' => Carbon::now()->subDays(3 + ($i % 10)),
                        'notes' => 'ساعات تطوع مسجّلة تلقائياً ضمن بيانات الاختبار.',
                    ]
                );
            }
        });
    }
}

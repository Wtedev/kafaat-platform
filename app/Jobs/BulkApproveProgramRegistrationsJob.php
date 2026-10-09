<?php

namespace App\Jobs;

use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramRegistration\BulkProgramRegistrationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class BulkApproveProgramRegistrationsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $registrationIds
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly int $actorId,
        public readonly int $programId,
        public readonly array $registrationIds,
        public readonly array $filters = [],
    ) {}

    public function handle(BulkProgramRegistrationProcessor $processor): void
    {
        $actor = User::query()->find($this->actorId);
        $program = TrainingProgram::query()->find($this->programId);

        if (! $actor instanceof User || ! $program instanceof TrainingProgram) {
            return;
        }

        $records = ProgramRegistration::query()
            ->where('training_program_id', $program->id)
            ->whereIn('id', $this->registrationIds)
            ->get();

        $summary = $processor->approve($records, $actor, $program, $this->filters);

        $processor->notifyActor(
            $actor,
            'اكتمل القبول الجماعي',
            'قُبل '.$summary['approved'].' من '.$summary['total']
                .($summary['capacity_blocked'] > 0
                    ? '، وتوقف '.$summary['capacity_blocked'].' بسبب امتلاء المقاعد.'
                    : '.'),
            $summary,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $actor = User::query()->find($this->actorId);
        if (! $actor instanceof User) {
            return;
        }

        app(BulkProgramRegistrationProcessor::class)->notifyActor(
            $actor,
            'تعذر القبول الجماعي',
            'حدث خطأ أثناء معالجة القبول الجماعي. حاول مرة أخرى.',
        );
    }
}

<?php

namespace App\Jobs;

use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\ProgramRegistration\BulkProgramRegistrationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class BulkRejectProgramRegistrationsJob implements ShouldQueue
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
        public readonly ?string $reason = null,
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

        $summary = $processor->reject($records, $actor, $program, $this->reason, $this->filters);

        $processor->notifyActor(
            $actor,
            'اكتمل الرفض الجماعي',
            'رُفض '.$summary['rejected'].' من '.$summary['total'].'.',
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
            'تعذر الرفض الجماعي',
            'حدث خطأ أثناء معالجة الرفض الجماعي. حاول مرة أخرى.',
        );
    }
}

<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\StaffUi\StaffRegistrationAvailability;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class StaffProgramIndex
{
    public function __construct(
        private readonly StaffProgramStatus $status,
    ) {}

    public function paginate(
        string $search,
        string $kind,
        string $programStatus,
        string $registration,
        int $page,
        int $perPage = 12,
    ): LengthAwarePaginator {
        return $this->query($search, $kind, $programStatus, $registration)
            ->with(['learningPath:id,title'])
            ->withCount([
                'registrations as pending_registrations_count' => fn (Builder $q) => $q
                    ->where('status', RegistrationStatus::Pending->value),
                'registrations as approved_registrations_count' => fn (Builder $q) => $q
                    ->where('status', RegistrationStatus::Approved->value),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', max(1, $page))
            ->withQueryString();
    }

    public function count(): int
    {
        return TrainingProgram::query()->count();
    }

    public function status(): StaffProgramStatus
    {
        return $this->status;
    }

    /**
     * @return Builder<TrainingProgram>
     */
    private function query(string $search, string $kind, string $programStatus, string $registration): Builder
    {
        $query = TrainingProgram::query();

        $needle = trim($search);
        if ($needle !== '') {
            // Escape LIKE wildcards only; keep "_" literal for Arabic/English titles.
            $like = '%'.addcslashes($needle, '%\\').'%';
            $query->where('title', 'like', $like);
        }

        if ($kind !== '' && TrainingProgramKind::tryFrom($kind) !== null) {
            $query->where('program_kind', $kind);
        }

        if ($programStatus !== '' && ProgramStatus::tryFrom($programStatus) !== null) {
            $query->where('status', $programStatus);
        }

        $this->applyRegistrationFilter($query, $registration);

        return $query;
    }

    /**
     * @param  Builder<TrainingProgram>  $query
     */
    private function applyRegistrationFilter(Builder $query, string $registration): void
    {
        $availability = StaffRegistrationAvailability::tryFrom($registration);
        if ($availability === null || $availability === StaffRegistrationAvailability::ManualClosed) {
            return;
        }

        $today = Carbon::today()->toDateString();
        $approvedSub = '(select count(*) from program_registrations'
            .' where program_registrations.training_program_id = training_programs.id'
            .' and program_registrations.status = ?)';

        match ($availability) {
            StaffRegistrationAvailability::Path => $query->whereNotNull('learning_path_id'),
            StaffRegistrationAvailability::NotStarted => $query
                ->whereNull('learning_path_id')
                ->whereNotNull('registration_start')
                ->whereDate('registration_start', '>', $today),
            StaffRegistrationAvailability::Closed => $query
                ->whereNull('learning_path_id')
                ->where(function (Builder $q) use ($today): void {
                    $q->whereNull('registration_start')
                        ->orWhereDate('registration_start', '<=', $today);
                })
                ->whereNotNull('registration_end')
                ->whereDate('registration_end', '<', $today),
            StaffRegistrationAvailability::Full => $query
                ->whereNull('learning_path_id')
                ->where(function (Builder $q) use ($today): void {
                    $q->whereNull('registration_start')
                        ->orWhereDate('registration_start', '<=', $today);
                })
                ->where(function (Builder $q) use ($today): void {
                    $q->whereNull('registration_end')
                        ->orWhereDate('registration_end', '>=', $today);
                })
                ->whereNotNull('capacity')
                ->whereRaw($approvedSub.' >= training_programs.capacity', [RegistrationStatus::Approved->value]),
            StaffRegistrationAvailability::Open => $query
                ->whereNull('learning_path_id')
                ->where(function (Builder $q) use ($today): void {
                    $q->whereNull('registration_start')
                        ->orWhereDate('registration_start', '<=', $today);
                })
                ->where(function (Builder $q) use ($today): void {
                    $q->whereNull('registration_end')
                        ->orWhereDate('registration_end', '>=', $today);
                })
                ->where(function (Builder $q) use ($approvedSub): void {
                    $q->whereNull('capacity')
                        ->orWhereRaw($approvedSub.' < training_programs.capacity', [RegistrationStatus::Approved->value]);
                }),
            StaffRegistrationAvailability::ManualClosed => null,
        };
    }
}

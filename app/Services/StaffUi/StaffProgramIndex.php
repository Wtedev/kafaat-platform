<?php

namespace App\Services\StaffUi;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TrainingProgramKind;
use App\Models\TrainingProgram;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class StaffProgramIndex
{
    public const PER_PAGE = 15;

    public function paginate(?string $search, ?string $kind, ?string $status): LengthAwarePaginator
    {
        $search = trim((string) $search);
        $kind = $this->kind($kind);
        $status = $this->status($status);

        $query = TrainingProgram::query()
            ->withCount([
                'registrations as pending_registrations_count' => function (Builder $registrations): void {
                    $registrations->where('status', RegistrationStatus::Pending->value);
                },
                'registrations as approved_registrations_count' => function (Builder $registrations): void {
                    $registrations->where('status', RegistrationStatus::Approved->value);
                },
            ]);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where('title', 'like', $like);
        }

        if ($kind !== null) {
            $query->where('program_kind', $kind);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function kind(?string $kind): ?string
    {
        $kind = trim((string) $kind);

        return TrainingProgramKind::tryFrom($kind)?->value;
    }

    private function status(?string $status): ?string
    {
        $status = trim((string) $status);

        return ProgramStatus::tryFrom($status)?->value;
    }
}

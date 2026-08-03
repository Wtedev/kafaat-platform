<?php

namespace App\Services\Exports;

use App\Enums\AttendanceStatus;
use App\Enums\AuditLogResult;
use App\Enums\RegistrationStatus;
use App\Exports\ProgramRegistrationsExport;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Sync Excel export for program registrants.
 * Collection is loaded in memory for typical cohort sizes; query/chunk helpers
 * stay ready for a queued job later without changing column authorization.
 */
final class ProgramRegistrationExportService
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_APPROVED = 'approved';

    public const SCOPE_PENDING = 'pending';

    public const SCOPE_REJECTED = 'rejected';

    public const SCOPE_CANCELLED = 'cancelled';

    public const SCOPE_COMPLETED = 'completed';

    public const SCOPE_ATTENDED = 'attended';

    public const SCOPE_ABSENT = 'absent';

    public const SCOPE_TABLE_FILTERS = 'table_filters';

    public const SCOPE_SELECTED = 'selected';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, string>
     */
    public static function scopeOptions(bool $includeTableFilters = true, bool $includeSelected = true): array
    {
        $options = [
            self::SCOPE_ALL => 'جميع المسجلين',
            self::SCOPE_APPROVED => 'المقبولون',
            self::SCOPE_PENDING => 'قيد الانتظار',
            self::SCOPE_REJECTED => 'المرفوضون',
            self::SCOPE_CANCELLED => 'الملغون',
            self::SCOPE_COMPLETED => 'المكتملون',
            self::SCOPE_ATTENDED => 'حاضرون (جلسة واحدة على الأقل)',
            self::SCOPE_ABSENT => 'غائبون (جلسة غياب واحدة على الأقل)',
        ];

        if ($includeTableFilters) {
            $options[self::SCOPE_TABLE_FILTERS] = 'وفق فلاتر الجدول الحالي';
        }

        if ($includeSelected) {
            $options[self::SCOPE_SELECTED] = 'الصفوف المحددة في الجدول';
        }

        return $options;
    }

    /**
     * @param  list<string>  $columnKeys
     * @param  list<int|string>|null  $selectedIds
     */
    public function count(
        TrainingProgram $program,
        string $scope,
        ?Builder $filteredTableQuery = null,
        ?array $selectedIds = null,
    ): int {
        return $this->baseQuery($program, $scope, $filteredTableQuery, $selectedIds)->count();
    }

    /**
     * @param  list<string>  $requestedColumnKeys
     * @param  list<int|string>|null  $selectedIds
     * @return BinaryFileResponse|null null when empty / invalid columns (caller notifies)
     */
    public function download(
        User $actor,
        TrainingProgram $program,
        array $requestedColumnKeys,
        string $scope = self::SCOPE_ALL,
        ?Builder $filteredTableQuery = null,
        ?array $selectedIds = null,
    ): ?BinaryFileResponse {
        abort_unless(ProgramRegistrationExportAuthorization::canExport($actor, $program), 403);

        $keys = ProgramRegistrationExportAuthorization::filterAllowedColumnKeys(
            $actor,
            $requestedColumnKeys,
        );

        if ($keys === []) {
            return null;
        }

        try {
            $registrations = $this->loadRegistrations(
                $program,
                $scope,
                $filteredTableQuery,
                $selectedIds,
            );

            if ($registrations->isEmpty()) {
                $this->auditLogger->record(
                    $actor,
                    'export.generated',
                    AuditLogResult::Failure,
                    resource: $program,
                    metadata: [
                        'export_type' => 'program_registrations',
                        'training_program_id' => $program->id,
                        'row_count' => 0,
                        'selected_columns' => $keys,
                        'scope' => $scope,
                        'reason' => 'empty_result',
                    ],
                    request: request(),
                );

                return null;
            }

            $filename = $this->filename($program);

            $this->auditLogger->record(
                $actor,
                'export.generated',
                AuditLogResult::Success,
                resource: $program,
                metadata: [
                    'export_type' => 'program_registrations',
                    'training_program_id' => $program->id,
                    'row_count' => $registrations->count(),
                    'selected_columns' => $keys,
                    'scope' => $scope,
                ],
                request: request(),
            );

            return Excel::download(
                new ProgramRegistrationsExport($registrations, $keys, $actor),
                $filename,
            );
        } catch (Throwable $exception) {
            $this->auditLogger->record(
                $actor,
                'export.generated',
                AuditLogResult::Failure,
                resource: $program,
                metadata: [
                    'export_type' => 'program_registrations',
                    'training_program_id' => $program->id,
                    'selected_columns' => $keys,
                    'scope' => $scope,
                    'exception' => $exception::class,
                ],
                request: request(),
            );

            throw $exception;
        }
    }

    /**
     * @param  list<int|string>|null  $selectedIds
     * @return Collection<int, ProgramRegistration>
     */
    public function loadRegistrations(
        TrainingProgram $program,
        string $scope,
        ?Builder $filteredTableQuery = null,
        ?array $selectedIds = null,
    ): Collection {
        return $this->baseQuery($program, $scope, $filteredTableQuery, $selectedIds)
            ->with(['user.profile', 'approvedBy'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Chunk callback for future queued exports.
     *
     * @param  callable(Collection<int, ProgramRegistration>): void  $callback
     * @param  list<int|string>|null  $selectedIds
     */
    public function chunkRegistrations(
        TrainingProgram $program,
        string $scope,
        callable $callback,
        int $size = 250,
        ?Builder $filteredTableQuery = null,
        ?array $selectedIds = null,
    ): void {
        $this->baseQuery($program, $scope, $filteredTableQuery, $selectedIds)
            ->with(['user.profile', 'approvedBy'])
            ->orderBy('id')
            ->chunkById($size, $callback);
    }

    public function filename(TrainingProgram $program): string
    {
        $slug = Str::slug((string) ($program->slug ?: $program->title), '-');
        if ($slug === '') {
            $slug = 'program-'.$program->id;
        }

        // Arabic-friendly label prefix (filesystem-safe ASCII slug + date)
        return 'مسجلو-برنامج-'.$slug.'-'.now()->timezone(config('app.timezone'))->format('Y-m-d').'.xlsx';
    }

    /**
     * @param  list<int|string>|null  $selectedIds
     * @return Builder<ProgramRegistration>
     */
    public function baseQuery(
        TrainingProgram $program,
        string $scope,
        ?Builder $filteredTableQuery = null,
        ?array $selectedIds = null,
    ): Builder {
        if ($scope === self::SCOPE_TABLE_FILTERS && $filteredTableQuery !== null) {
            /** @var Builder<ProgramRegistration> $query */
            $query = $filteredTableQuery->getModel() instanceof ProgramRegistration
                ? clone $filteredTableQuery
                : $program->registrations()->getQuery();

            // Hard scope to this program even if table query is reused incorrectly
            $query->where($query->getModel()->getTable().'.training_program_id', $program->id);

            return $query;
        }

        /** @var Builder<ProgramRegistration> $query */
        $query = ProgramRegistration::query()
            ->where('training_program_id', $program->id);

        return match ($scope) {
            self::SCOPE_APPROVED => $query->where('status', RegistrationStatus::Approved),
            self::SCOPE_PENDING => $query->where('status', RegistrationStatus::Pending),
            self::SCOPE_REJECTED => $query->where('status', RegistrationStatus::Rejected),
            self::SCOPE_CANCELLED => $query->where('status', RegistrationStatus::Cancelled),
            self::SCOPE_COMPLETED => $query->where('status', RegistrationStatus::Completed),
            self::SCOPE_ATTENDED => $query->whereHas(
                'attendanceRecords',
                fn (Builder $q) => $q->whereIn('status', AttendanceStatus::attendedValues()),
            ),
            self::SCOPE_ABSENT => $query->whereHas(
                'attendanceRecords',
                fn (Builder $q) => $q->where('status', AttendanceStatus::Absent),
            ),
            self::SCOPE_SELECTED => $query->whereIn(
                'id',
                array_values(array_filter($selectedIds ?? [], fn ($id) => filled($id))),
            ),
            default => $query,
        };
    }
}

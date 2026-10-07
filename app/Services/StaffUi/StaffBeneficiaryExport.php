<?php

namespace App\Services\StaffUi;

use App\Enums\AuditLogResult;
use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Exports\BeneficiaryProfilesExport;
use App\Jobs\ExportStaffBeneficiaryProfiles;
use App\Models\InboxNotification;
use App\Models\Profile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Exports\BeneficiaryExportAuthorization;
use App\Support\Exports\BeneficiaryProfileExportColumns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * يصدّر ملفات المستفيدين بنفس أعمدة Filament وصلاحياته، مع فلاتر صفحة الموظفين.
 */
final class StaffBeneficiaryExport
{
    public const SYNC_ROW_LIMIT = 5000;

    public const HOURLY_LIMIT = 10;

    public function __construct(
        private readonly StaffBeneficiaryIndex $index,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columnOptions(User $actor): array
    {
        $options = BeneficiaryProfileExportColumns::optionLabels();

        if (! $actor->can('exports.beneficiaries.contact')) {
            unset($options['user_email'], $options['user_phone']);
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public function defaultColumnKeys(User $actor): array
    {
        return array_values(array_intersect(
            BeneficiaryExportAuthorization::defaultColumnKeysFor($actor),
            array_keys($this->columnOptions($actor)),
        ));
    }

    public static function rateLimitKey(User $actor): string
    {
        return 'staff-beneficiary-export:'.$actor->id;
    }

    public static function filename(): string
    {
        return 'مستفيدين-كفاءات-'.now()->format('Y-m-d').'.xlsx';
    }

    /**
     * @param  list<string>  $requestedKeys
     */
    public function downloadOrQueue(
        User $actor,
        string $search,
        string $status,
        string $completeness,
        array $requestedKeys,
    ): BinaryFileResponse|RedirectResponse {
        abort_unless($actor->can('export', Profile::class), 403);

        $keys = $this->authorizedKeys($actor, $requestedKeys);
        $this->consumeRateLimit($actor);

        $filters = $this->filters($search, $status, $completeness);
        $profiles = $this->profiles($search, $status, $completeness);

        if ($profiles->isEmpty()) {
            throw ValidationException::withMessages([
                'columns' => 'لا توجد ملفات مستفيدين للتصدير.',
            ]);
        }

        if ($profiles->count() > $this->syncLimit()) {
            ExportStaffBeneficiaryProfiles::dispatch(
                $actor->id,
                $search,
                $status,
                $completeness,
                $keys,
            );

            return back()->with('status', 'سيصلك إشعار برابط تنزيل الملف. الرابط صالح لمدة 24 ساعة.');
        }

        $this->recordAudit($actor, $profiles->count(), $keys, $filters);

        return Excel::download(new BeneficiaryProfilesExport($profiles, $keys), self::filename());
    }

    /**
     * @param  list<string>  $requestedKeys
     */
    public function storeForActor(
        int $actorId,
        string $search,
        string $status,
        string $completeness,
        array $requestedKeys,
    ): void {
        $actor = User::query()->find($actorId);
        if (! $actor instanceof User || ! $actor->can('export', Profile::class)) {
            return;
        }

        $keys = BeneficiaryExportAuthorization::filterAllowedColumnKeys($actor, $requestedKeys);
        if ($keys === []) {
            return;
        }

        $profiles = $this->profiles($search, $status, $completeness);
        if ($profiles->isEmpty()) {
            return;
        }

        $filters = $this->filters($search, $status, $completeness);
        $token = Str::random(40);
        $relative = 'staff-beneficiary-exports/'.$actor->id.'/'.$token.'.xlsx';

        Excel::store(new BeneficiaryProfilesExport($profiles, $keys), $relative, 'local');

        $url = URL::temporarySignedRoute(
            'staff-ui.users.export.download',
            now()->addHours(24),
            ['token' => $token, 'exporter' => $actor->id],
        );

        InboxNotification::query()->create([
            'user_id' => $actor->id,
            'title' => 'تصدير المستفيدين جاهز',
            'message' => 'ملف Excel جاهز للتنزيل خلال 24 ساعة.',
            'type' => InboxNotificationType::GeneralMessage,
            'sender_id' => null,
            'target_type' => NotificationTargetType::SingleUser,
            'context' => ['download_url' => $url],
        ]);

        $this->recordAudit($actor, $profiles->count(), $keys, $filters);
    }

    public function downloadStored(User $actor, string $token): StreamedResponse
    {
        abort_unless($actor->can('export', Profile::class), 403);
        abort_unless(preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1, 404);

        $relative = 'staff-beneficiary-exports/'.$actor->id.'/'.$token.'.xlsx';
        abort_unless(Storage::disk('local')->exists($relative), 404);

        return Storage::disk('local')->download($relative, self::filename());
    }

    /**
     * @param  list<string>  $requestedKeys
     * @return list<string>
     */
    private function authorizedKeys(User $actor, array $requestedKeys): array
    {
        $allowed = array_keys(BeneficiaryProfileExportColumns::optionLabels());
        $keys = array_values(array_intersect($requestedKeys, $allowed));
        $contact = array_values(array_intersect($keys, ['user_email', 'user_phone']));

        if ($contact !== [] && ! $actor->can('exports.beneficiaries.contact')) {
            throw ValidationException::withMessages([
                'columns' => 'أعمدة التواصل غير متاحة لحسابك.',
            ]);
        }

        $keys = BeneficiaryExportAuthorization::filterAllowedColumnKeys($actor, $keys);

        if ($keys === []) {
            throw ValidationException::withMessages([
                'columns' => 'اختر عموداً واحداً على الأقل.',
            ]);
        }

        return $keys;
    }

    private function consumeRateLimit(User $actor): void
    {
        $key = self::rateLimitKey($actor);

        if (RateLimiter::tooManyAttempts($key, self::HOURLY_LIMIT)) {
            abort(429, 'تجاوزت حد التصدير. يمكنك التصدير 10 مرات كل ساعة.');
        }

        RateLimiter::hit($key, 3600);
    }

    /**
     * @return Collection<int, Profile>
     */
    private function profiles(string $search, string $status, string $completeness): Collection
    {
        $users = $this->index->matching($search, $status, $completeness);

        return $users
            ->map(fn (User $user): ?Profile => $user->profile)
            ->filter(fn (?Profile $profile): bool => $profile instanceof Profile)
            ->each(function (Profile $profile) use ($users): void {
                $owner = $users->firstWhere('id', $profile->user_id);
                if ($owner instanceof User) {
                    $profile->setRelation('user', $owner);
                }
            })
            ->values();
    }

    /**
     * @param  list<string>  $keys
     * @param  array{q: string, status: string, profile: string}  $filters
     */
    private function recordAudit(User $actor, int $rowCount, array $keys, array $filters): void
    {
        $this->auditLogger->record(
            $actor,
            'export.generated',
            AuditLogResult::Success,
            metadata: [
                'export_type' => 'beneficiary_profiles',
                'row_count' => $rowCount,
                'selected_columns' => $keys,
                'filters' => $filters,
            ],
            request: request(),
        );
    }

    /**
     * @return array{q: string, status: string, profile: string}
     */
    private function filters(string $search, string $status, string $completeness): array
    {
        return [
            'q' => $search,
            'status' => $status,
            'profile' => $completeness,
        ];
    }

    private function syncLimit(): int
    {
        return max(0, (int) config('staff_ui.beneficiary_export_sync_limit', self::SYNC_ROW_LIMIT));
    }
}

<?php

namespace App\Services\Certificates;

use App\Enums\RegistrationStatus;
use App\Jobs\IssueEligibleCertificatesJob;
use App\Models\Certificate;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class CertificateIssueBatch
{
    public function __construct(private readonly CertificateEligibilityService $eligibility) {}

    /**
     * @param  Collection<int, Model>|null  $only
     * @return array{new: int, existing: int}
     */
    public function preview(Model $owner, ?Collection $only = null): array
    {
        $registrations = $this->registrations($owner, $only);
        $results = $this->eligibility->evaluateMany($registrations);
        $issuedUserIds = $this->issuedUserIds($owner, $registrations);

        $new = 0;
        $existing = 0;

        foreach ($registrations as $registration) {
            if (isset($issuedUserIds[(int) $registration->user_id])) {
                $existing++;

                continue;
            }

            if ($results->get($registration->getKey())?->eligible) {
                $new++;
            }
        }

        return ['new' => $new, 'existing' => $existing];
    }

    /**
     * @param  Collection<int, Model>|null  $only
     */
    public function dispatch(Model $owner, User $actor, ?Collection $only = null): ?string
    {
        $registrations = $this->registrations($owner, $only);
        $results = $this->eligibility->evaluateMany($registrations);
        $issuedUserIds = $this->issuedUserIds($owner, $registrations);
        $jobs = [];

        foreach ($registrations as $registration) {
            if (isset($issuedUserIds[(int) $registration->user_id])) {
                continue;
            }

            if (! $results->get($registration->getKey())?->eligible) {
                continue;
            }

            $jobs[] = new IssueEligibleCertificatesJob((int) $registration->getKey(), (int) $actor->id, $registration::class);
        }

        if ($jobs === []) {
            return null;
        }

        $actorId = (int) $actor->id;
        $batch = Bus::batch($jobs)
            ->name('issue-certificates-'.$owner->getKey())
            ->onQueue('certificates')
            ->allowFailures()
            ->finally(function ($batch) use ($actorId): void {
                $user = User::query()->find($actorId);
                if (! $user instanceof User) {
                    return;
                }

                $failed = (int) $batch->failedJobs;
                $succeeded = max(0, (int) $batch->totalJobs - $failed);

                Notification::make()
                    ->title('اكتمل إصدار الشهادات')
                    ->body($succeeded.' نجحت، '.$failed.' فشلت')
                    ->sendToDatabase($user);
            })
            ->dispatch();

        return $batch->id;
    }

    /**
     * @param  Collection<int, Model>|null  $only
     * @return Collection<int, Model>
     */
    private function registrations(Model $owner, ?Collection $only): Collection
    {
        if ($only instanceof Collection) {
            return $only->values();
        }

        return $owner->registrations()
            ->whereIn('status', [
                RegistrationStatus::Approved->value,
                RegistrationStatus::Completed->value,
            ])
            ->get();
    }

    /**
     * @param  Collection<int, Model>  $registrations
     * @return array<int, true>
     */
    private function issuedUserIds(Model $owner, Collection $registrations): array
    {
        $userIds = $registrations->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        if ($userIds === []) {
            return [];
        }

        return Certificate::query()
            ->active()
            ->where('certificateable_type', $owner->getMorphClass())
            ->where('certificateable_id', $owner->getKey())
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }
}

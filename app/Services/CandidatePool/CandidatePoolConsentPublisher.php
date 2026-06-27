<?php

namespace App\Services\CandidatePool;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\CandidatePoolConsentVersion;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CandidatePoolConsentPublisher
{
    public function publish(CandidatePoolConsentVersion $version): CandidatePoolConsentVersion
    {
        if (! $version->isDraft()) {
            throw new InvalidArgumentException('Only draft versions can be published.');
        }

        $sanitized = PrivacyPolicyHtmlSanitizer::sanitize((string) $version->content);
        $hash = PrivacyPolicyContentHasher::hash($sanitized);

        return DB::transaction(function () use ($version, $sanitized, $hash): CandidatePoolConsentVersion {
            CandidatePoolConsentVersion::query()
                ->where('status', PrivacyPolicyVersionStatus::Active)
                ->lockForUpdate()
                ->get()
                ->each(fn (CandidatePoolConsentVersion $active) => $active->forceFill(['status' => PrivacyPolicyVersionStatus::Archived])->save());

            $version->forceFill([
                'content' => $sanitized,
                'content_hash' => $hash,
                'status' => PrivacyPolicyVersionStatus::Active,
                'published_at' => now(),
                'effective_at' => $version->effective_at ?? now(),
            ])->save();

            CandidatePoolConsentVersionService::forgetCache();

            return $version->fresh();
        });
    }

    public function createDraftFromVersion(CandidatePoolConsentVersion $source, string $newVersion, ?int $userId = null): CandidatePoolConsentVersion
    {
        if ($source->isDraft()) {
            throw new InvalidArgumentException('Cannot clone an unpublished draft as a new draft source.');
        }

        return CandidatePoolConsentVersion::query()->create([
            'version' => $newVersion,
            'title' => $source->title,
            'content' => $source->content,
            'content_hash' => '',
            'effective_at' => now(),
            'published_at' => null,
            'status' => PrivacyPolicyVersionStatus::Draft,
            'requires_reconsent' => $source->requires_reconsent,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}

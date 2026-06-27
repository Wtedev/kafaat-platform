<?php

namespace App\Services\Privacy;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\PrivacyPolicyVersion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PrivacyPolicyPublisher
{
    public function publish(PrivacyPolicyVersion $version): PrivacyPolicyVersion
    {
        if (! $version->isDraft()) {
            throw new InvalidArgumentException('Only draft versions can be published.');
        }

        if (blank($version->version) || blank($version->title) || blank($version->content)) {
            throw new InvalidArgumentException('Version, title, and content are required before publishing.');
        }

        $sanitizedContent = PrivacyPolicyHtmlSanitizer::sanitize((string) $version->content);
        $contentHash = PrivacyPolicyContentHasher::hash($sanitizedContent);

        return DB::transaction(function () use ($version, $sanitizedContent, $contentHash): PrivacyPolicyVersion {
            $locked = PrivacyPolicyVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isDraft()) {
                throw new RuntimeException('Privacy policy version is no longer publishable.');
            }

            PrivacyPolicyVersion::query()
                ->where('status', PrivacyPolicyVersionStatus::Active)
                ->lockForUpdate()
                ->get()
                ->each(function (PrivacyPolicyVersion $active): void {
                    $active->forceFill([
                        'status' => PrivacyPolicyVersionStatus::Archived,
                    ])->save();
                });

            $now = now();

            $locked->forceFill([
                'content' => $sanitizedContent,
                'content_hash' => $contentHash,
                'status' => PrivacyPolicyVersionStatus::Active,
                'published_at' => $now,
                'effective_at' => $locked->effective_at ?? $now,
            ])->save();

            PrivacyPolicyService::forgetCache();

            return $locked->fresh();
        });
    }

    public function createDraftFromVersion(PrivacyPolicyVersion $source, string $newVersion, ?int $userId = null): PrivacyPolicyVersion
    {
        if ($source->status === PrivacyPolicyVersionStatus::Draft) {
            throw new InvalidArgumentException('Cannot clone an unpublished draft as a new draft source.');
        }

        return PrivacyPolicyVersion::query()->create([
            'version' => $newVersion,
            'title' => $source->title,
            'content' => $source->content,
            'content_hash' => '',
            'effective_at' => now(),
            'published_at' => null,
            'status' => PrivacyPolicyVersionStatus::Draft,
            'requires_reacknowledgement' => $source->requires_reacknowledgement,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}

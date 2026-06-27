<?php

namespace Database\Seeders;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\CandidatePoolConsentVersion;
use App\Services\CandidatePool\CandidatePoolConsentVersionService;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use Illuminate\Database\Seeder;

class CandidatePoolConsentSeeder extends Seeder
{
    public function run(): void
    {
        if (CandidatePoolConsentVersion::query()->where('version', '1.0')->exists()) {
            return;
        }

        $content = PrivacyPolicyHtmlSanitizer::sanitize((string) config('candidate_pool.consent_checkbox_intro'));
        $publishedAt = '2026-06-29 00:00:00';

        CandidatePoolConsentVersion::query()->create([
            'version' => '1.0',
            'title' => 'موافقة قاعدة المرشحين الداخلية',
            'content' => $content,
            'content_hash' => PrivacyPolicyContentHasher::hash($content),
            'status' => PrivacyPolicyVersionStatus::Active,
            'requires_reconsent' => false,
            'effective_at' => $publishedAt,
            'published_at' => $publishedAt,
        ]);

        CandidatePoolConsentVersionService::forgetCache();
    }
}

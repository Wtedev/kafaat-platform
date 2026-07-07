<?php

namespace Database\Seeders;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use App\Services\Privacy\PrivacyPolicyPublisher;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Database\Seeder;

class PrivacyPolicyGenderUpdateSeeder extends Seeder
{
    public function run(): void
    {
        if (PrivacyPolicyVersion::query()->where('version', '1.1')->exists()) {
            return;
        }

        $content = PrivacyPolicyHtmlSanitizer::sanitize(PrivacyPolicyContent::body());
        $publishedAt = now();

        $draft = PrivacyPolicyVersion::query()->create([
            'version' => '1.1',
            'title' => 'سياسة الخصوصية',
            'content' => $content,
            'content_hash' => PrivacyPolicyContentHasher::hash($content),
            'effective_at' => $publishedAt,
            'published_at' => null,
            'status' => PrivacyPolicyVersionStatus::Draft,
            'requires_reacknowledgement' => true,
        ]);

        app(PrivacyPolicyPublisher::class)->publish($draft);

        $this->command?->info('PrivacyPolicyGenderUpdateSeeder: published privacy policy v1.1.');
    }
}

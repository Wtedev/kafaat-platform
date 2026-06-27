<?php

namespace Database\Factories;

use App\Enums\PrivacyPolicyVersionStatus;
use App\Models\PrivacyPolicyVersion;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use App\Services\Privacy\PrivacyPolicyHtmlSanitizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrivacyPolicyVersion>
 */
class PrivacyPolicyVersionFactory extends Factory
{
    protected $model = PrivacyPolicyVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = PrivacyPolicyHtmlSanitizer::sanitize('<section><h2>سياسة تجريبية</h2><p>محتوى اختبار.</p></section>');

        return [
            'version' => 'test-'.fake()->unique()->numerify('###'),
            'title' => 'سياسة الخصوصية',
            'content' => $content,
            'content_hash' => PrivacyPolicyContentHasher::hash($content),
            'effective_at' => now(),
            'published_at' => null,
            'status' => PrivacyPolicyVersionStatus::Draft,
            'requires_reacknowledgement' => false,
        ];
    }

    public function active(bool $requiresReacknowledgement = false): static
    {
        return $this->state(function (array $attributes) use ($requiresReacknowledgement): array {
            $content = $attributes['content'] ?? PrivacyPolicyHtmlSanitizer::sanitize('<section><p>محتوى.</p></section>');

            return [
                'content' => $content,
                'content_hash' => PrivacyPolicyContentHasher::hash((string) $content),
                'status' => PrivacyPolicyVersionStatus::Active,
                'published_at' => now(),
                'requires_reacknowledgement' => $requiresReacknowledgement,
            ];
        });
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => PrivacyPolicyVersionStatus::Archived,
            'published_at' => now()->subDay(),
        ]);
    }
}

<?php

namespace App\Data\Privacy;

final readonly class PrivacyAccessResponseSnapshot
{
    /**
     * @param  list<array{category: string, summary: string, sources: list<string>}>  $categories
     */
    public function __construct(
        public array $categories,
        public string $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'generated_at' => $this->generatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            categories: is_array($data['categories'] ?? null) ? $data['categories'] : [],
            generatedAt: (string) ($data['generated_at'] ?? now()->toIso8601String()),
        );
    }
}

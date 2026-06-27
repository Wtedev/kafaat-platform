<?php

namespace App\Data\Privacy;

final readonly class DeletionPlanSnapshot
{
    /**
     * @param  array<string, DeletionPlanResourceDecision>  $resources
     */
    public function __construct(
        public array $resources,
        public string $generatedAt,
        public int $policyCatalogVersion = 1,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $resources = [];

        foreach ($this->resources as $type => $decision) {
            $resources[$type] = $decision->toArray();
        }

        return [
            'resources' => $resources,
            'generated_at' => $this->generatedAt,
            'policy_catalog_version' => $this->policyCatalogVersion,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $resources = [];

        foreach (($data['resources'] ?? []) as $type => $resourceData) {
            if (! is_array($resourceData)) {
                continue;
            }

            $resources[(string) $type] = DeletionPlanResourceDecision::fromArray([
                ...$resourceData,
                'resource_type' => $type,
            ]);
        }

        return new self(
            resources: $resources,
            generatedAt: (string) ($data['generated_at'] ?? now()->toIso8601String()),
            policyCatalogVersion: (int) ($data['policy_catalog_version'] ?? 1),
        );
    }

    public function decisionFor(string $resourceType): ?DeletionPlanResourceDecision
    {
        return $this->resources[$resourceType] ?? null;
    }
}

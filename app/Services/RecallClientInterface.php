<?php

declare(strict_types=1);

namespace App\Services;

interface RecallClientInterface
{
    /**
     * Search for relevant memories.
     *
     * @return array<array{content: string, source: string|null}>
     */
    public function search(string $query, ?string $agentId = null, int $limit = 10): array;

    /**
     * Store a new memory.
     */
    public function store(
        string $content,
        float $importance = 0.5,
        ?string $agentId = null,
        ?string $source = null,
        ?string $category = null,
    ): ?int;

    /**
     * Get a specific memory by ID.
     */
    public function get(int $id): ?array;
}

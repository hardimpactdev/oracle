<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class RecallClient implements RecallClientInterface
{
    private readonly Client $client;

    public function __construct(?string $baseUrl = null)
    {
        /** @var string $url */
        $url = $baseUrl ?? config('oracle.recall_url', 'https://recall.beast');

        $this->client = new Client([
            'base_uri' => rtrim($url, '/'),
            'timeout' => 10,
            'verify' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Search for relevant memories.
     *
     * @return array<array{content: string, source: string|null}>
     */
    public function search(string $query, ?string $agentId = null, int $limit = 10): array
    {
        $params = [
            'query' => $query,
            'limit' => $limit,
        ];

        if ($agentId !== null) {
            $params['agent_id'] = $agentId;
        }

        try {
            $response = $this->client->post('/api/memories/search', [
                'json' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (! is_array($data) || ! isset($data['data'])) {
                return [];
            }

            return array_map(fn (array $memory): array => [
                'content' => $memory['content'] ?? '',
                'source' => $memory['source'] ?? null,
            ], $data['data']);
        } catch (GuzzleException) {
            return [];
        }
    }

    /**
     * Store a new memory.
     */
    public function store(
        string $content,
        float $importance = 0.5,
        ?string $agentId = null,
        ?string $source = null,
        ?string $category = null,
    ): ?int {
        $params = [
            'content' => $content,
            'importance' => $importance,
        ];

        if ($agentId !== null) {
            $params['agent_id'] = $agentId;
        }

        if ($source !== null) {
            $params['source'] = $source;
        }

        if ($category !== null) {
            $params['metadata'] = ['category' => $category];
        }

        try {
            $response = $this->client->post('/api/memories', [
                'json' => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return is_array($data) && isset($data['data']['id']) ? (int) $data['data']['id'] : null;
        } catch (GuzzleException) {
            return null;
        }
    }

    /**
     * Get a specific memory by ID.
     */
    public function get(int $id): ?array
    {
        try {
            $response = $this->client->get("/api/memories/{$id}");

            $data = json_decode($response->getBody()->getContents(), true);

            return is_array($data) && isset($data['data']) ? $data['data'] : null;
        } catch (GuzzleException) {
            return null;
        }
    }
}

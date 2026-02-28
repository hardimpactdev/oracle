<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ReviewResult
{
    /**
     * @param  'approve'|'request_changes'  $verdict
     * @param  array<array{path: string, line: int|null, body: string}>  $comments
     * @param  array<array{project: string|null, description: string, severity: string}>  $frictionPoints
     */
    public function __construct(
        public string $verdict,
        public string $summary,
        public array $comments = [],
        public array $frictionPoints = [],
    ) {}

    /**
     * @return array{verdict: string, summary: string, comments: array<array{path: string, line: int|null, body: string}>, friction_points: array<array{project: string|null, description: string, severity: string}>}
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'summary' => $this->summary,
            'comments' => $this->comments,
            'friction_points' => $this->frictionPoints,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            verdict: $data['verdict'] ?? 'request_changes',
            summary: $data['summary'] ?? '',
            comments: $data['comments'] ?? [],
            frictionPoints: $data['friction_points'] ?? $data['workarounds'] ?? [],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PlanResult
{
    /**
     * @param  array<array{title: string, description: string, files: array<string>, verification: string}>  $steps
     * @param  array{docs: array<string>, memories: array<string>}  $contextUsed
     */
    public function __construct(
        public array $steps,
        public string $summary,
        public array $contextUsed = ['docs' => [], 'memories' => []],
    ) {}

    /**
     * @return array{steps: array<array{title: string, description: string, files: array<string>, verification: string}>, summary: string, context_used: array{docs: array<string>, memories: array<string>}}
     */
    public function toArray(): array
    {
        return [
            'steps' => $this->steps,
            'summary' => $this->summary,
            'context_used' => $this->contextUsed,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            steps: $data['steps'] ?? [],
            summary: $data['summary'] ?? '',
            contextUsed: $data['context_used'] ?? ['docs' => [], 'memories' => []],
        );
    }
}

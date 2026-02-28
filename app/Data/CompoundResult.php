<?php

declare(strict_types=1);

namespace App\Data;

final readonly class CompoundResult
{
    /**
     * @param  array<int, array{action: string, file: string, content?: string, reason: string, existing_file?: string}>  $learnings
     * @param  array<int, array{package: string, title: string, description: string, severity: string}>  $packageTasks
     */
    public function __construct(
        public array $learnings = [],
        public array $packageTasks = [],
        public string $summary = '',
    ) {}

    /**
     * @return array{learnings: array<int, array<string, mixed>>, package_tasks: array<int, array<string, mixed>>, summary: string}
     */
    public function toArray(): array
    {
        return [
            'learnings' => $this->learnings,
            'package_tasks' => $this->packageTasks,
            'summary' => $this->summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            learnings: is_array($data['learnings'] ?? null) ? $data['learnings'] : [],
            packageTasks: is_array($data['package_tasks'] ?? null) ? $data['package_tasks'] : [],
            summary: is_string($data['summary'] ?? null) ? $data['summary'] : '',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PlanResult
{
    /**
     * @param  array<array{title: string, description: string, files: array<string>, verification: string}>  $steps
     * @param  array{docs: array<string>, memories: array<string>}  $contextUsed
     * @param  array{detected: bool, evidence: string, confidence: float}|null  $alreadyImplemented
     * @param  array{risk_level: 'low'|'medium'|'high'|'critical', concerns: array<string>, requires_review: bool}|null  $impactAnalysis
     */
    public function __construct(
        public array $steps,
        public string $summary,
        public array $contextUsed = ['docs' => [], 'memories' => []],
        public ?array $alreadyImplemented = null,
        public ?array $impactAnalysis = null,
    ) {}

    /**
     * @return array{steps: array<array{title: string, description: string, files: array<string>, verification: string}>, summary: string, context_used: array{docs: array<string>, memories: array<string>}, already_implemented: array{detected: bool, evidence: string, confidence: float}|null, impact_analysis: array{risk_level: string, concerns: array<string>, requires_review: bool}|null}
     */
    public function toArray(): array
    {
        return [
            'steps' => $this->steps,
            'summary' => $this->summary,
            'context_used' => $this->contextUsed,
            'already_implemented' => $this->alreadyImplemented,
            'impact_analysis' => $this->impactAnalysis,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            steps: $data['steps'] ?? [],
            summary: $data['summary'] ?? '',
            contextUsed: $data['context_used'] ?? ['docs' => [], 'memories' => []],
            alreadyImplemented: is_array($data['already_implemented'] ?? null) ? $data['already_implemented'] : null,
            impactAnalysis: is_array($data['impact_analysis'] ?? null) ? $data['impact_analysis'] : null,
        );
    }
}

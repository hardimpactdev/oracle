<?php

declare(strict_types=1);

namespace App\Data;

final readonly class VerifyResult
{
    /**
     * @param  'pass'|'follow_up'|'package_issue'|'fail'  $verdict
     * @param  array<array{package: string, description: string, severity: string, blocking: bool}>  $packageIssues
     */
    public function __construct(
        public string $verdict,
        public string $summary,
        public ?string $followUpQuestion = null,
        public array $packageIssues = [],
        public float $confidence = 0.0,
    ) {}

    /**
     * @return array{verdict: string, summary: string, follow_up_question: string|null, package_issues: array<array{package: string, description: string, severity: string, blocking: bool}>, confidence: float}
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'summary' => $this->summary,
            'follow_up_question' => $this->followUpQuestion,
            'package_issues' => $this->packageIssues,
            'confidence' => $this->confidence,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            verdict: $data['verdict'] ?? 'fail',
            summary: $data['summary'] ?? '',
            followUpQuestion: $data['follow_up_question'] ?? null,
            packageIssues: $data['package_issues'] ?? [],
            confidence: isset($data['confidence']) && (is_float($data['confidence']) || is_int($data['confidence']))
                ? (float) $data['confidence']
                : 0.0,
        );
    }
}

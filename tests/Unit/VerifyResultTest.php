<?php

declare(strict_types=1);

use App\Data\VerifyResult;

describe('VerifyResult', function () {
    describe('fromArray', function () {
        it('creates a pass result', function () {
            $result = VerifyResult::fromArray([
                'verdict' => 'pass',
                'summary' => 'All beads completed successfully.',
                'confidence' => 0.95,
            ]);

            expect($result->verdict)->toBe('pass')
                ->and($result->summary)->toBe('All beads completed successfully.')
                ->and($result->followUpQuestion)->toBeNull()
                ->and($result->packageIssues)->toBe([])
                ->and($result->confidence)->toBe(0.95);
        });

        it('creates a follow_up result with question', function () {
            $result = VerifyResult::fromArray([
                'verdict' => 'follow_up',
                'summary' => 'Unclear if edge case is handled.',
                'follow_up_question' => 'Does the implementation handle empty arrays?',
                'confidence' => 0.6,
            ]);

            expect($result->verdict)->toBe('follow_up')
                ->and($result->followUpQuestion)->toBe('Does the implementation handle empty arrays?')
                ->and($result->confidence)->toBe(0.6);
        });

        it('creates a package_issue result with issues', function () {
            $issues = [
                [
                    'package' => 'spatie/laravel-data',
                    'description' => 'Missing cast support for nested DTOs',
                    'severity' => 'medium',
                    'blocking' => false,
                ],
            ];

            $result = VerifyResult::fromArray([
                'verdict' => 'package_issue',
                'summary' => 'Implementation works but package has a gap.',
                'package_issues' => $issues,
                'confidence' => 0.85,
            ]);

            expect($result->verdict)->toBe('package_issue')
                ->and($result->packageIssues)->toBe($issues)
                ->and($result->confidence)->toBe(0.85);
        });

        it('creates a fail result', function () {
            $result = VerifyResult::fromArray([
                'verdict' => 'fail',
                'summary' => 'Fundamentally wrong approach.',
                'confidence' => 0.9,
            ]);

            expect($result->verdict)->toBe('fail')
                ->and($result->summary)->toBe('Fundamentally wrong approach.');
        });

        it('defaults missing fields', function () {
            $result = VerifyResult::fromArray([]);

            expect($result->verdict)->toBe('fail')
                ->and($result->summary)->toBe('')
                ->and($result->followUpQuestion)->toBeNull()
                ->and($result->packageIssues)->toBe([])
                ->and($result->confidence)->toBe(0.0);
        });
    });

    describe('toArray', function () {
        it('maps camelCase to snake_case keys', function () {
            $result = new VerifyResult(
                verdict: 'follow_up',
                summary: 'Need more info.',
                followUpQuestion: 'What about tests?',
                packageIssues: [['package' => 'foo', 'description' => 'bar', 'severity' => 'low', 'blocking' => false]],
                confidence: 0.7,
            );

            $array = $result->toArray();

            expect($array)->toHaveKeys(['verdict', 'summary', 'follow_up_question', 'package_issues', 'confidence'])
                ->and($array['follow_up_question'])->toBe('What about tests?')
                ->and($array['package_issues'])->toHaveCount(1)
                ->and($array['confidence'])->toBe(0.7);
        });

        it('roundtrips through fromArray and toArray', function () {
            $input = [
                'verdict' => 'pass',
                'summary' => 'All good.',
                'follow_up_question' => null,
                'package_issues' => [],
                'confidence' => 0.95,
            ];

            $result = VerifyResult::fromArray($input);

            expect($result->toArray())->toBe($input);
        });
    });
});

<?php

declare(strict_types=1);

use App\Data\ProjectContext;
use App\Services\PromptBuilder;

describe('PromptBuilder', function () {
    beforeEach(function () {
        $this->builder = new PromptBuilder;
    });

    describe('buildPlanPrompt', function () {
        it('includes task description', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildPlanPrompt('Add dark mode support', $context);

            expect($prompt)
                ->toContain('Add dark mode support')
                ->toContain('structured implementation plan')
                ->toContain('"steps"');
        });

        it('includes conventions when available', function () {
            $context = new ProjectContext(
                projectPath: '/tmp/test',
                conventions: '# Project Rules\nUse strict types everywhere.',
            );

            $prompt = $this->builder->buildPlanPrompt('Add feature', $context);

            expect($prompt)->toContain('Use strict types everywhere');
        });

        it('includes completion criteria from task meta', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');
            $meta = ['completion_criteria' => ['All tests pass', 'No PHPStan errors']];

            $prompt = $this->builder->buildPlanPrompt('Fix bug', $context, $meta);

            expect($prompt)
                ->toContain('All tests pass')
                ->toContain('No PHPStan errors');
        });

        it('includes review feedback when present', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');
            $meta = ['review_feedback' => 'Missing error handling in the controller'];

            $prompt = $this->builder->buildPlanPrompt('Fix bug', $context, $meta);

            expect($prompt)
                ->toContain('Previous Review Feedback')
                ->toContain('Missing error handling in the controller');
        });

        it('includes memories when available', function () {
            $context = new ProjectContext(
                projectPath: '/tmp/test',
                memories: [
                    ['content' => 'Always use lockForUpdate in transactions', 'source' => 'oracle'],
                ],
            );

            $prompt = $this->builder->buildPlanPrompt('Add feature', $context);

            expect($prompt)->toContain('Always use lockForUpdate in transactions');
        });
    });

    describe('buildReviewPrompt', function () {
        it('includes diff', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildReviewPrompt('+ added line', $context);

            expect($prompt)
                ->toContain('+ added line')
                ->toContain('```diff')
                ->toContain('"verdict"');
        });

        it('includes internal projects for workaround detection', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildReviewPrompt('diff', $context, null, ['orbit', 'recall']);

            expect($prompt)
                ->toContain('orbit, recall')
                ->toContain('friction points');
        });

        it('includes task description when provided', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildReviewPrompt('diff', $context, 'Implement caching layer');

            expect($prompt)->toContain('Implement caching layer');
        });
    });

    describe('buildVerifyPrompt', function () {
        it('includes transcript task description and beads status', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildVerifyPrompt(
                '[{"role":"user","content":"hello"}]',
                'Implement dark mode',
                '{"total":5,"completed":5}',
                $context,
            );

            expect($prompt)
                ->toContain('[{"role":"user","content":"hello"}]')
                ->toContain('Implement dark mode')
                ->toContain('"total":5,"completed":5')
                ->toContain('"verdict"')
                ->toContain('expert verification agent');
        });

        it('includes solutions index when provided', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildVerifyPrompt(
                'transcript',
                'task',
                '{}',
                $context,
                "- architecture/task-gate.md\n- logic-errors/status-bug.md",
            );

            expect($prompt)
                ->toContain('Solutions Index')
                ->toContain('task-gate.md');
        });

        it('includes completion criteria from task meta', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');
            $meta = ['completion_criteria' => ['All tests pass', 'No PHPStan errors']];

            $prompt = $this->builder->buildVerifyPrompt('transcript', 'task', '{}', $context, null, $meta);

            expect($prompt)
                ->toContain('Completion Criteria')
                ->toContain('All tests pass')
                ->toContain('No PHPStan errors');
        });

        it('includes previous review feedback from task meta', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');
            $meta = ['previous_review_feedback' => 'Missing error handling'];

            $prompt = $this->builder->buildVerifyPrompt('transcript', 'task', '{}', $context, null, $meta);

            expect($prompt)
                ->toContain('Previous Review Feedback')
                ->toContain('Missing error handling');
        });

        it('appends project context', function () {
            $context = new ProjectContext(
                projectPath: '/tmp/test',
                conventions: '# Rules: Use strict types.',
                memories: [['content' => 'Always use lockForUpdate', 'source' => 'oracle']],
            );

            $prompt = $this->builder->buildVerifyPrompt('transcript', 'task', '{}', $context);

            expect($prompt)
                ->toContain('Use strict types')
                ->toContain('Always use lockForUpdate');
        });
    });

    describe('buildAskPrompt', function () {
        it('includes the question', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildAskPrompt('How does auth work?', $context);

            expect($prompt)
                ->toContain('How does auth work?')
                ->toContain('Answer the following question');
        });
    });

    describe('buildLearnPrompt', function () {
        it('includes content and source', function () {
            $context = new ProjectContext(projectPath: '/tmp/test');

            $prompt = $this->builder->buildLearnPrompt('diff content', 'PR #123', $context);

            expect($prompt)
                ->toContain('diff content')
                ->toContain('PR #123')
                ->toContain('"learnings"');
        });
    });
});

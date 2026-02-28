<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Concerns\WithLlmInvocation;
use App\Concerns\WithProjectContext;
use App\Data\ReviewResult;
use App\Drivers\LlmDriver;
use App\Services\ConfigManager;
use App\Services\ContextGatherer;
use App\Services\PromptBuilder;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

final class ReviewCommand extends Command
{
    use WithJsonOutput;
    use WithLlmInvocation;
    use WithProjectContext;

    protected $signature = 'review
        {--pr= : PR URL to review}
        {--diff= : Git diff reference (e.g. HEAD~3)}
        {--branch= : Branch to compare against main}
        {--task-file= : Path to task metadata JSON (for context)}
        {--project= : Path to the project (defaults to cwd)}
        {--driver= : LLM driver (gemini, claude, codex)}
        {--model= : LLM model to use}
        {--detect-workarounds : Enable friction point detection}
        {--json : Output as JSON}';

    protected $description = 'Review code changes against project conventions';

    public function handle(
        ConfigManager $config,
        ContextGatherer $gatherer,
        PromptBuilder $promptBuilder,
        LlmDriver $driver,
    ): int {
        try {
            $projectPath = $this->resolveProjectPath();
            $config->loadProject($projectPath);
        } catch (\RuntimeException $e) {
            return $this->failWithMessage($e->getMessage());
        }

        $driverName = $this->resolveDriver($config);
        $driver = $this->resolveDriverInstance($driverName);
        $model = $this->resolveModel($config);
        $timeout = $this->resolveTimeout($config);

        // Get the diff
        $diff = $this->resolveDiff($projectPath);
        if ($diff === null) {
            return $this->failWithMessage('No diff could be generated. Provide --pr, --diff, or --branch.');
        }

        if (! $this->wantsJson()) {
            $this->info("Reviewing with {$driverName}/{$model}...");
        }

        // Gather context
        $context = $gatherer->gather($projectPath, 'code review');

        // Read task description from file if provided
        $taskDescription = $this->readTaskDescription();

        // Internal projects for workaround detection
        $internalProjects = $this->option('detect-workarounds')
            ? ($config->projectGet('internal_projects') ?? [])
            : null;

        // Build prompt
        $prompt = $promptBuilder->buildReviewPrompt($diff, $context, $taskDescription, $internalProjects);

        // Invoke LLM
        try {
            $result = $this->invokeLlm($driver, $model, $prompt, $projectPath, $timeout);
        } catch (\RuntimeException $e) {
            return $this->failWithMessage($e->getMessage());
        }

        if ($result === null) {
            return $this->failWithMessage('Failed to parse LLM response as JSON.');
        }

        $review = ReviewResult::fromArray($result);

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess($review->toArray());
        }

        $this->renderReview($review);

        return self::SUCCESS;
    }

    private function readTaskDescription(): ?string
    {
        /** @var string|null $taskFile */
        $taskFile = $this->option('task-file');

        if ($taskFile === null || ! is_file($taskFile)) {
            return null;
        }

        $content = file_get_contents($taskFile);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return null;
        }

        $parts = [];
        if (isset($decoded['title']) && is_string($decoded['title'])) {
            $parts[] = $decoded['title'];
        }
        if (isset($decoded['description']) && is_string($decoded['description'])) {
            $parts[] = $decoded['description'];
        }

        return $parts !== [] ? implode("\n\n", $parts) : null;
    }

    private function resolveDiff(string $projectPath): ?string
    {
        /** @var string|null $pr */
        $pr = $this->option('pr');
        if ($pr !== null) {
            return $this->getDiffFromPr($pr, $projectPath);
        }

        /** @var string|null $diff */
        $diff = $this->option('diff');
        if ($diff !== null) {
            $result = Process::path($projectPath)->run(['git', 'diff', $diff]);

            return $result->successful() ? $result->output() : null;
        }

        /** @var string|null $branch */
        $branch = $this->option('branch');
        if ($branch !== null) {
            $result = Process::path($projectPath)->run(['git', 'diff', 'main...'.$branch]);

            return $result->successful() ? $result->output() : null;
        }

        // Default: unstaged + staged changes
        $result = Process::path($projectPath)->run(['git', 'diff', 'HEAD']);

        return $result->successful() && mb_trim($result->output()) !== '' ? $result->output() : null;
    }

    private function getDiffFromPr(string $prUrl, string $projectPath): ?string
    {
        // Extract PR number from URL
        if (preg_match('/\/pull\/(\d+)/', $prUrl, $matches) === 1) {
            $result = Process::path($projectPath)->run(['gh', 'pr', 'diff', $matches[1]]);

            return $result->successful() ? $result->output() : null;
        }

        return null;
    }

    private function resolveDriverInstance(string $driverName): LlmDriver
    {
        return match ($driverName) {
            'claude' => new \App\Drivers\ClaudeDriver,
            'codex' => new \App\Drivers\CodexDriver,
            default => new \App\Drivers\GeminiDriver,
        };
    }

    private function renderReview(ReviewResult $review): void
    {
        $this->newLine();

        $verdictColor = $review->verdict === 'approve' ? 'green' : 'red';
        $verdictLabel = $review->verdict === 'approve' ? 'APPROVED' : 'CHANGES REQUESTED';
        $this->line("<fg={$verdictColor};options=bold>{$verdictLabel}</>");
        $this->newLine();
        $this->line($review->summary);

        if ($review->comments !== []) {
            $this->newLine();
            $this->line('<fg=cyan;options=bold>Comments:</>');

            foreach ($review->comments as $comment) {
                $location = $comment['path'];
                if (isset($comment['line']) && $comment['line'] !== null) {
                    $location .= ':'.$comment['line'];
                }
                $this->line("<fg=gray>{$location}</>");
                $this->line("  {$comment['body']}");
                $this->newLine();
            }
        }

        if ($review->frictionPoints !== []) {
            $this->newLine();
            $this->line('<fg=yellow;options=bold>Friction Points:</>');

            foreach ($review->frictionPoints as $point) {
                $project = $point['project'] ?? 'unknown';
                $severity = $point['severity'] ?? 'medium';
                $this->line("<fg=yellow>[{$severity}] {$project}:</> {$point['description']}");
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Concerns\WithLlmInvocation;
use App\Concerns\WithProjectContext;
use App\Data\VerifyResult;
use App\Drivers\LlmDriver;
use App\Services\ConfigManager;
use App\Services\ContextGatherer;
use App\Services\PromptBuilder;
use LaravelZero\Framework\Commands\Command;

final class VerifyCommand extends Command
{
    use WithJsonOutput;
    use WithLlmInvocation;
    use WithProjectContext;

    protected $signature = 'verify
        {--transcript-file= : Path to session transcript JSON}
        {--task-file= : Path to task metadata JSON}
        {--beads-status= : JSON string with beads completion data}
        {--solutions-index= : Path to solutions INDEX.yaml}
        {--project= : Path to the project (defaults to cwd)}
        {--driver= : LLM driver (gemini, claude, codex)}
        {--model= : LLM model to use}
        {--json : Output as JSON}';

    protected $description = 'Verify coder work against task requirements and beads';

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

        // Read transcript
        $transcript = $this->readTranscript();
        if ($transcript === null) {
            return $this->failWithMessage('Could not read transcript. Provide --transcript-file with a valid path.');
        }

        // Read task file
        $taskData = $this->readTaskFile();
        if ($taskData === null) {
            return $this->failWithMessage('Could not read task file. Provide --task-file with a valid path.');
        }

        $taskDescription = $taskData['description'] ?? $taskData['title'] ?? 'No description provided.';
        $taskMeta = $taskData['meta'] ?? null;

        // Parse beads status
        $beadsStatus = $this->readBeadsStatus();

        // Read solutions index
        $solutionsIndex = $this->readSolutionsIndex();

        if (! $this->wantsJson()) {
            $this->info("Verifying with {$driverName}/{$model}...");
        }

        // Gather context
        $context = $gatherer->gather($projectPath, 'verification');

        // Build prompt
        $prompt = $promptBuilder->buildVerifyPrompt(
            $transcript,
            $taskDescription,
            $beadsStatus,
            $context,
            $solutionsIndex,
            $taskMeta,
        );

        // Invoke LLM
        try {
            $result = $this->invokeLlm($driver, $model, $prompt, $projectPath, $timeout);
        } catch (\RuntimeException $e) {
            return $this->failWithMessage($e->getMessage());
        }

        if ($result === null) {
            return $this->failWithMessage('Failed to parse LLM response as JSON.');
        }

        $verification = VerifyResult::fromArray($result);

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess($verification->toArray());
        }

        $this->renderVerification($verification);

        return self::SUCCESS;
    }

    private function readTranscript(): ?string
    {
        /** @var string|null $file */
        $file = $this->option('transcript-file');
        if ($file === null || ! is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);

        return $content !== false ? $content : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readTaskFile(): ?array
    {
        /** @var string|null $file */
        $file = $this->option('task-file');
        if ($file === null || ! is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function readBeadsStatus(): string
    {
        /** @var string|null $beads */
        $beads = $this->option('beads-status');

        return $beads ?? '{}';
    }

    private function readSolutionsIndex(): ?string
    {
        /** @var string|null $file */
        $file = $this->option('solutions-index');
        if ($file === null || ! is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);

        return $content !== false ? $content : null;
    }

    private function resolveDriverInstance(string $driverName): LlmDriver
    {
        return match ($driverName) {
            'claude' => new \App\Drivers\ClaudeDriver,
            'codex' => new \App\Drivers\CodexDriver,
            default => new \App\Drivers\GeminiDriver,
        };
    }

    private function renderVerification(VerifyResult $verification): void
    {
        $this->newLine();

        $verdictColor = match ($verification->verdict) {
            'pass' => 'green',
            'follow_up' => 'yellow',
            'package_issue' => 'cyan',
            default => 'red',
        };

        $verdictLabel = match ($verification->verdict) {
            'pass' => 'PASS',
            'follow_up' => 'FOLLOW-UP REQUIRED',
            'package_issue' => 'PASS (PACKAGE ISSUE)',
            'fail' => 'FAIL',
            default => mb_strtoupper($verification->verdict),
        };

        $this->line("<fg={$verdictColor};options=bold>{$verdictLabel}</>");
        $this->newLine();
        $this->line($verification->summary);

        if ($verification->followUpQuestion !== null) {
            $this->newLine();
            $this->line('<fg=yellow;options=bold>Follow-up Question:</>');
            $this->line($verification->followUpQuestion);
        }

        if ($verification->packageIssues !== []) {
            $this->newLine();
            $this->line('<fg=cyan;options=bold>Package Issues:</>');

            foreach ($verification->packageIssues as $issue) {
                $package = $issue['package'] ?? 'unknown';
                $severity = $issue['severity'] ?? 'medium';
                $blocking = ($issue['blocking'] ?? false) ? ' [BLOCKING]' : '';
                $this->line("<fg=cyan>[{$severity}] {$package}{$blocking}:</> {$issue['description']}");
            }
        }

        $this->newLine();
        $confidencePercent = (int) round($verification->confidence * 100);
        $this->line("<fg=gray>Confidence: {$confidencePercent}%</>");
    }
}

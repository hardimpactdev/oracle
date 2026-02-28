<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Concerns\WithLlmInvocation;
use App\Concerns\WithProjectContext;
use App\Data\CompoundResult;
use App\Drivers\LlmDriver;
use App\Services\ConfigManager;
use App\Services\ContextGatherer;
use App\Services\PromptBuilder;
use LaravelZero\Framework\Commands\Command;

final class CompoundCommand extends Command
{
    use WithJsonOutput;
    use WithLlmInvocation;
    use WithProjectContext;

    protected $signature = 'compound
        {--transcript-file= : Path to session transcript JSON}
        {--task-file= : Path to task metadata JSON}
        {--solutions-index= : Path to solutions INDEX.yaml}
        {--packages= : Path to packages.md}
        {--project= : Path to the project (defaults to cwd)}
        {--driver= : LLM driver (gemini, claude, codex)}
        {--model= : LLM model to use}
        {--json : Output as JSON}';

    protected $description = 'Extract learnings and solution docs from a coder session transcript';

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
        $taskDescription = $this->buildTaskDescription($taskData);

        // Read optional context files
        $solutionsIndex = $this->readFileOption('solutions-index');
        $packagesDoc = $this->readFileOption('packages');

        if (! $this->wantsJson()) {
            $this->info("Compounding with {$driverName}/{$model}...");
        }

        // Gather context
        $context = $gatherer->gather($projectPath, 'knowledge extraction');

        // Build prompt
        $prompt = $promptBuilder->buildCompoundPrompt(
            $transcript,
            $taskDescription,
            $context,
            $solutionsIndex,
            $packagesDoc,
            $taskData['meta'] ?? null,
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

        $compound = CompoundResult::fromArray($result);

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess($compound->toArray());
        }

        $this->renderCompound($compound);

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

    /**
     * @param  array<string, mixed>|null  $taskData
     */
    private function buildTaskDescription(?array $taskData): string
    {
        if ($taskData === null) {
            return 'No task description provided.';
        }

        $parts = [];
        if (isset($taskData['title']) && is_string($taskData['title'])) {
            $parts[] = $taskData['title'];
        }
        if (isset($taskData['description']) && is_string($taskData['description'])) {
            $parts[] = $taskData['description'];
        }

        return $parts !== [] ? implode("\n\n", $parts) : 'No task description provided.';
    }

    private function readFileOption(string $optionName): ?string
    {
        /** @var string|null $file */
        $file = $this->option($optionName);
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

    private function renderCompound(CompoundResult $compound): void
    {
        $this->newLine();

        if ($compound->summary !== '') {
            $this->line('<fg=cyan;options=bold>Summary:</>');
            $this->line($compound->summary);
            $this->newLine();
        }

        if ($compound->learnings !== []) {
            $this->line('<fg=green;options=bold>Learnings:</>');
            foreach ($compound->learnings as $learning) {
                $action = $learning['action'] ?? 'unknown';
                $file = $learning['file'] ?? 'unknown';
                $reason = $learning['reason'] ?? '';
                $this->line("<fg=green>[{$action}]</> {$file}");
                if ($reason !== '') {
                    $this->line("  {$reason}");
                }
            }
            $this->newLine();
        }

        if ($compound->packageTasks !== []) {
            $this->line('<fg=yellow;options=bold>Package Tasks:</>');
            foreach ($compound->packageTasks as $task) {
                $package = $task['package'] ?? 'unknown';
                $title = $task['title'] ?? '';
                $severity = $task['severity'] ?? 'medium';
                $this->line("<fg=yellow>[{$severity}] {$package}:</> {$title}");
            }
        }
    }
}

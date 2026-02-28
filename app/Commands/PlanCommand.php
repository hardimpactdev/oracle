<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Concerns\WithLlmInvocation;
use App\Concerns\WithProjectContext;
use App\Data\PlanResult;
use App\Drivers\LlmDriver;
use App\Services\ConfigManager;
use App\Services\ContextGatherer;
use App\Services\PromptBuilder;
use LaravelZero\Framework\Commands\Command;

final class PlanCommand extends Command
{
    use WithJsonOutput;
    use WithLlmInvocation;
    use WithProjectContext;

    protected $signature = 'plan
        {task? : The task description (inline)}
        {--file= : Read task from a JSON file}
        {--stdin : Read task from stdin}
        {--project= : Path to the project (defaults to cwd)}
        {--driver= : LLM driver (gemini, claude, codex)}
        {--model= : LLM model to use}
        {--json : Output as JSON}
        {--output-dir= : Write plan to directory}';

    protected $description = 'Generate a structured implementation plan from a task description';

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

        // Resolve the driver based on config hierarchy
        $driverName = $this->resolveDriver($config);
        $driver = $this->resolveDriverInstance($driverName);
        $model = $this->resolveModel($config);
        $timeout = $this->resolveTimeout($config);

        // Read task input
        $taskDescription = $this->readTaskInput();
        if ($taskDescription === null) {
            return $this->failWithMessage('No task provided. Pass a task description, --file, or --stdin.');
        }

        $taskMeta = $this->readTaskMeta();

        if (! $this->wantsJson()) {
            $this->info("Planning with {$driverName}/{$model}...");
        }

        // Gather context
        $context = $gatherer->gather($projectPath, $taskDescription);

        // Build prompt
        $prompt = $promptBuilder->buildPlanPrompt($taskDescription, $context, $taskMeta);

        // Invoke LLM
        try {
            $result = $this->invokeLlm($driver, $model, $prompt, $projectPath, $timeout);
        } catch (\RuntimeException $e) {
            return $this->failWithMessage($e->getMessage());
        }

        if ($result === null) {
            return $this->failWithMessage('Failed to parse LLM response as JSON.');
        }

        $plan = PlanResult::fromArray($result);

        // Add context tracking
        $planArray = $plan->toArray();
        $planArray['context_used'] = [
            'docs' => $context->docPaths(),
            'memories' => array_column($context->memories, 'source'),
        ];

        // Write to output dir if requested
        /** @var string|null $outputDir */
        $outputDir = $this->option('output-dir');
        if ($outputDir !== null) {
            $this->writeOutput($planArray, $outputDir);
        }

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess($planArray);
        }

        $this->renderPlan($plan);

        return self::SUCCESS;
    }

    private function readTaskInput(): ?string
    {
        if ($this->option('stdin')) {
            $input = file_get_contents('php://stdin');

            return $input !== false ? mb_trim($input) : null;
        }

        /** @var string|null $file */
        $file = $this->option('file');
        if ($file !== null) {
            if (! is_file($file)) {
                return null;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                return null;
            }

            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['description'])) {
                return (string) $decoded['description'];
            }

            return mb_trim($content);
        }

        /** @var string|null $task */
        $task = $this->argument('task');

        return $task;
    }

    private function readTaskMeta(): ?array
    {
        /** @var string|null $file */
        $file = $this->option('file');
        if ($file === null || ! is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['meta'])) {
            return $decoded['meta'];
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

    private function renderPlan(PlanResult $plan): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>Plan Summary:</>');
        $this->line($plan->summary);
        $this->newLine();

        foreach ($plan->steps as $i => $step) {
            $num = $i + 1;
            $this->line("<fg=green;options=bold>Step {$num}: {$step['title']}</>");
            $this->line($step['description']);

            if (isset($step['files']) && $step['files'] !== []) {
                $this->line('<fg=gray>Files: '.implode(', ', $step['files']).'</>');
            }

            if (isset($step['verification'])) {
                $this->line('<fg=gray>Verify: '.$step['verification'].'</>');
            }

            $this->newLine();
        }
    }

    private function writeOutput(array $planArray, string $outputDir): void
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents(
            $outputDir.'/plan.json',
            json_encode($planArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

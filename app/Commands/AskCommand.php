<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Concerns\WithLlmInvocation;
use App\Concerns\WithProjectContext;
use App\Drivers\LlmDriver;
use App\Services\ConfigManager;
use App\Services\ContextGatherer;
use App\Services\PromptBuilder;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

final class AskCommand extends Command
{
    use WithJsonOutput;
    use WithLlmInvocation;
    use WithProjectContext;

    protected $signature = 'ask
        {question : The question to answer}
        {--file= : Include file content as additional context}
        {--project= : Path to the project (defaults to cwd)}
        {--driver= : LLM driver (gemini, claude, codex)}
        {--model= : LLM model to use}
        {--deep : Research codebase before answering}
        {--json : Output as JSON}';

    protected $description = 'Answer a question using project context and Recall memories';

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

        /** @var string $question */
        $question = $this->argument('question');

        // Optionally include file content
        /** @var string|null $file */
        $file = $this->option('file');
        if ($file !== null && is_file($file)) {
            $fileContent = file_get_contents($file);
            if ($fileContent !== false) {
                $question .= "\n\n## Additional Context (from {$file})\n\n{$fileContent}";
            }
        }

        if (! $this->wantsJson()) {
            $this->info("Thinking with {$driverName}/{$model}...");
        }

        // Gather context
        $context = $gatherer->gather($projectPath, $question);

        // Build prompt
        $prompt = $promptBuilder->buildAskPrompt($question, $context);

        // For ask, we want the raw text response — not parsed JSON
        $promptFile = tempnam(sys_get_temp_dir(), 'oracle_prompt_');
        if ($promptFile === false) {
            return $this->failWithMessage('Failed to create temporary prompt file.');
        }

        try {
            file_put_contents($promptFile, $prompt);

            $command = $driver->buildCommand($promptFile, $model);

            $result = Process::path($projectPath)
                ->env($this->processEnv())
                ->timeout($timeout)
                ->run($command);

            if (! $result->successful()) {
                return $this->failWithMessage(
                    $driver->name().' CLI failed: '.mb_substr($result->errorOutput(), 0, 500)
                );
            }

            $response = $driver->parseOutput($result->output());
        } finally {
            @unlink($promptFile);
        }

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess([
                'answer' => $response->text,
                'context_used' => [
                    'docs' => $context->docPaths(),
                    'memories' => array_column($context->memories, 'source'),
                ],
            ]);
        }

        $this->newLine();
        $this->line($response->text);

        return self::SUCCESS;
    }

    private function resolveDriverInstance(string $driverName): LlmDriver
    {
        return match ($driverName) {
            'claude' => new \App\Drivers\ClaudeDriver,
            'codex' => new \App\Drivers\CodexDriver,
            default => new \App\Drivers\GeminiDriver,
        };
    }
}

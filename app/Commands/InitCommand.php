<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Services\ConfigManager;
use LaravelZero\Framework\Commands\Command;

final class InitCommand extends Command
{
    use WithJsonOutput;

    protected $signature = 'init
        {--driver=gemini : Default LLM driver}
        {--model= : Default LLM model}
        {--auto : Non-interactive mode}
        {--json : Output as JSON}';

    protected $description = 'Initialize Oracle for a project';

    public function handle(ConfigManager $config): int
    {
        $projectPath = (string) getcwd();

        if (is_file($projectPath.'/.oracle.json')) {
            return $this->failWithMessage('.oracle.json already exists in this directory.');
        }

        /** @var string $driver */
        $driver = $this->option('driver');

        /** @var string|null $model */
        $model = $this->option('model');

        if (! $this->option('auto') && $this->input->isInteractive()) {
            $driver = $this->choice('Default LLM driver?', ['gemini', 'claude', 'codex'], 0);

            $model = $this->ask('Default model?', $this->defaultModelFor($driver));

            /** @var string|null $recallAgentId */
            $recallAgentId = $this->ask('Recall agent ID? (for memory scoping)', 'oracle-'.basename($projectPath));
        } else {
            $model ??= $this->defaultModelFor($driver);
            $recallAgentId = 'oracle-'.basename($projectPath);
        }

        $projectConfig = [
            'driver' => $driver,
            'model' => $model,
            'recall_agent_id' => $recallAgentId ?? 'oracle-'.basename($projectPath),
            'context_paths' => $this->detectContextPaths($projectPath),
            'conventions_file' => $this->detectConventionsFile($projectPath),
        ];

        $config->setProjectConfig($projectConfig);
        $config->saveProject($projectPath);

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess($projectConfig);
        }

        $this->info('Created .oracle.json');
        $this->line('  Driver: '.$driver);
        $this->line('  Model: '.$model);
        $this->line('  Recall Agent: '.($recallAgentId ?? 'oracle-'.basename($projectPath)));

        return self::SUCCESS;
    }

    private function defaultModelFor(string $driver): string
    {
        return match ($driver) {
            'claude' => 'claude-sonnet-4-5-20250514',
            'codex' => 'codex-mini-latest',
            default => 'gemini-2.5-flash',
        };
    }

    /**
     * @return array<string>
     */
    private function detectContextPaths(string $projectPath): array
    {
        $paths = [];

        foreach (['AGENTS.md', 'CLAUDE.md'] as $file) {
            if (is_file($projectPath.'/'.$file)) {
                $paths[] = $file;
            }
        }

        if (is_dir($projectPath.'/docs/solutions')) {
            $paths[] = 'docs/solutions/';
        }

        return $paths;
    }

    private function detectConventionsFile(string $projectPath): ?string
    {
        foreach (['AGENTS.md', 'CLAUDE.md'] as $file) {
            if (is_file($projectPath.'/'.$file)) {
                return $file;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithJsonOutput;
use App\Services\ConfigManager;
use LaravelZero\Framework\Commands\Command;

final class ConfigCommand extends Command
{
    use WithJsonOutput;

    protected $signature = 'config
        {action? : Action to perform (get, set)}
        {key? : Config key to get or set}
        {value? : Value to set}
        {--json : Output as JSON}';

    protected $description = 'Manage Oracle configuration';

    public function handle(ConfigManager $config): int
    {
        /** @var string|null $action */
        $action = $this->argument('action');

        if ($action === null) {
            return $this->showConfig($config);
        }

        return match ($action) {
            'get' => $this->getConfig($config),
            'set' => $this->setConfig($config),
            default => $this->failWithMessage("Unknown action: {$action}. Use 'get' or 'set'."),
        };
    }

    private function showConfig(ConfigManager $config): int
    {
        $projectPath = (string) getcwd();
        $config->loadProject($projectPath);

        $global = $config->get() ?? [];
        $project = $config->projectGet() ?? [];

        $resolved = [
            'driver' => $config->resolve('driver', config('oracle.driver')),
            'model' => $config->resolve('model', config('oracle.model')),
            'timeout' => $config->resolve('timeout', config('oracle.timeout')),
            'recall_url' => $config->resolve('recall_url', config('oracle.recall_url')),
        ];

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess([
                'global' => $global,
                'project' => $project,
                'resolved' => $resolved,
            ]);
        }

        $this->line('<fg=cyan;options=bold>Global Config</>');
        $this->line('  Path: '.$config->getGlobalConfigDir().'/config.json');

        if (is_array($global) && $global !== []) {
            foreach ($global as $key => $value) {
                $this->line("  {$key}: ".json_encode($value));
            }
        } else {
            $this->line('  (empty)');
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Project Config</>');
        $this->line('  Path: '.$projectPath.'/.oracle.json');

        if (is_array($project) && $project !== []) {
            foreach ($project as $key => $value) {
                $this->line("  {$key}: ".json_encode($value));
            }
        } else {
            $this->line('  (not initialized — run `oracle init`)');
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Resolved Config</>');
        foreach ($resolved as $key => $value) {
            $this->line("  {$key}: ".json_encode($value));
        }

        return self::SUCCESS;
    }

    private function getConfig(ConfigManager $config): int
    {
        /** @var string|null $key */
        $key = $this->argument('key');

        if ($key === null) {
            return $this->failWithMessage('Provide a config key to get.');
        }

        $value = $config->get($key);

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess(['key' => $key, 'value' => $value]);
        }

        if ($value === null) {
            $this->line("{$key}: (not set)");
        } else {
            $this->line("{$key}: ".json_encode($value));
        }

        return self::SUCCESS;
    }

    private function setConfig(ConfigManager $config): int
    {
        /** @var string|null $key */
        $key = $this->argument('key');
        /** @var string|null $value */
        $value = $this->argument('value');

        if ($key === null || $value === null) {
            return $this->failWithMessage('Usage: oracle config set <key> <value>');
        }

        // Try to decode JSON values (for arrays/objects)
        $decoded = json_decode($value, true);
        $config->set($key, $decoded !== null && json_last_error() === JSON_ERROR_NONE ? $decoded : $value);

        if ($this->wantsJson()) {
            return $this->outputJsonSuccess(['key' => $key, 'value' => $config->get($key)]);
        }

        $this->info("Set {$key} = {$value}");

        return self::SUCCESS;
    }
}

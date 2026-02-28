<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\ConfigManager;

trait WithProjectContext
{
    protected function resolveProjectPath(): string
    {
        /** @var string $path */
        $path = $this->option('project') ?: getcwd();

        $realpath = realpath($path);
        if ($realpath === false || ! is_dir($realpath)) {
            throw new \RuntimeException("Project path does not exist: {$path}");
        }

        return $realpath;
    }

    protected function resolveDriver(ConfigManager $config): string
    {
        /** @var string */
        return $this->option('driver')
            ?? $config->projectGet('driver')
            ?? $config->get('driver')
            ?? config('oracle.driver', 'gemini');
    }

    protected function resolveModel(ConfigManager $config): string
    {
        /** @var string */
        return $this->option('model')
            ?? $config->projectGet('model')
            ?? $config->get('model')
            ?? config('oracle.model', 'gemini-2.5-flash');
    }

    protected function resolveTimeout(ConfigManager $config): int
    {
        return (int) ($config->projectGet('timeout')
            ?? $config->get('timeout')
            ?? config('oracle.timeout', 180));
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

final class ConfigManager
{
    private string $globalConfigPath;

    /** @var array<string, mixed> */
    private array $globalConfig = [];

    /** @var array<string, mixed> */
    private array $projectConfig = [];

    private ?string $projectPath = null;

    public function __construct()
    {
        $this->globalConfigPath = $this->getGlobalConfigDir().'/config.json';
        $this->loadGlobal();
    }

    public function getGlobalConfigDir(): string
    {
        return (getenv('HOME') ?: '/home/oracle').'/.config/oracle';
    }

    public function loadGlobal(): void
    {
        if (File::exists($this->globalConfigPath)) {
            $this->globalConfig = json_decode(File::get($this->globalConfigPath), true) ?? [];
        }
    }

    public function loadProject(string $projectPath): void
    {
        $this->projectPath = $projectPath;
        $configFile = $projectPath.'/.oracle.json';

        if (File::exists($configFile)) {
            $this->projectConfig = json_decode(File::get($configFile), true) ?? [];
        } else {
            $this->projectConfig = [];
        }
    }

    public function save(): void
    {
        $dir = dirname($this->globalConfigPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        File::put(
            $this->globalConfigPath,
            json_encode($this->globalConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function saveProject(string $projectPath): void
    {
        File::put(
            $projectPath.'/.oracle.json',
            json_encode($this->projectConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->globalConfig;
        }

        return data_get($this->globalConfig, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        data_set($this->globalConfig, $key, $value);
        $this->save();
    }

    public function projectGet(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->projectConfig;
        }

        return data_get($this->projectConfig, $key, $default);
    }

    public function projectSet(string $key, mixed $value): void
    {
        data_set($this->projectConfig, $key, $value);
    }

    public function setProjectConfig(array $config): void
    {
        $this->projectConfig = $config;
    }

    public function hasProjectConfig(): bool
    {
        return $this->projectPath !== null
            && File::exists($this->projectPath.'/.oracle.json');
    }

    /**
     * Resolve a config value with priority: project > global > app default.
     */
    public function resolve(string $key, mixed $default = null): mixed
    {
        return $this->projectGet($key)
            ?? $this->get($key)
            ?? $default;
    }
}

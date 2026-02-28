<?php

declare(strict_types=1);

namespace App\Providers;

use App\Drivers\ClaudeDriver;
use App\Drivers\CodexDriver;
use App\Drivers\GeminiDriver;
use App\Drivers\LlmDriver;
use App\Services\ConfigManager;
use App\Services\RecallClient;
use App\Services\RecallClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigManager::class);

        $this->app->singleton(RecallClientInterface::class, function ($app): RecallClient {
            $config = $app->make(ConfigManager::class);

            /** @var string $url */
            $url = $config->resolve('recall_url') ?? config('oracle.recall_url');

            return new RecallClient($url);
        });

        $this->app->bind(LlmDriver::class, function ($app): LlmDriver {
            $config = $app->make(ConfigManager::class);

            /** @var string $driver */
            $driver = $config->resolve('driver') ?? config('oracle.driver', 'gemini');

            return match ($driver) {
                'claude' => new ClaudeDriver,
                'codex' => new CodexDriver,
                default => new GeminiDriver,
            };
        });
    }

    public function boot(): void
    {
        //
    }
}

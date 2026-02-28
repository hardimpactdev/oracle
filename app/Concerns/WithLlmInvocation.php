<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Drivers\LlmDriver;
use App\Services\ResponseParser;
use Illuminate\Support\Facades\Process;

trait WithLlmInvocation
{
    protected function invokeLlm(
        LlmDriver $driver,
        string $model,
        string $prompt,
        string $workingDirectory,
        int $timeout = 180,
    ): ?array {
        $promptFile = tempnam(sys_get_temp_dir(), 'oracle_prompt_');
        if ($promptFile === false) {
            throw new \RuntimeException('Failed to create temporary prompt file');
        }

        try {
            file_put_contents($promptFile, $prompt);

            $command = $driver->buildCommand($promptFile, $model);

            $result = Process::path($workingDirectory)
                ->env($this->processEnv())
                ->timeout($timeout)
                ->run($command);

            if (! $result->successful()) {
                throw new \RuntimeException(
                    $driver->name().' CLI failed: '.mb_substr($result->errorOutput(), 0, 500)
                );
            }

            $response = $driver->parseOutput($result->output());

            $parser = app(ResponseParser::class);

            return $parser->parseJson($response->text, $workingDirectory);
        } finally {
            @unlink($promptFile);
        }
    }

    protected function processEnv(): array
    {
        /** @var string $extraPath */
        $extraPath = config('oracle.extra_path', '');

        if ($extraPath === '') {
            return [];
        }

        $currentPath = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';

        return ['PATH' => $extraPath.':'.$currentPath];
    }
}

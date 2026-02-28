<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Data\LlmResponse;

final readonly class CodexDriver implements LlmDriver
{
    public function buildCommand(string $promptFile, string $model): array
    {
        $prompt = file_get_contents($promptFile);

        // Codex doesn't support file input, so we pass the prompt directly
        return ['codex', '-p', $prompt !== false ? $prompt : '', '--model', $model];
    }

    public function parseOutput(string $output): LlmResponse
    {
        // Codex outputs raw text — no envelope to unwrap
        return new LlmResponse(text: mb_trim($output));
    }

    public function name(): string
    {
        return 'codex';
    }
}

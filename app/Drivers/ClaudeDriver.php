<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Data\LlmResponse;

final readonly class ClaudeDriver implements LlmDriver
{
    public function buildCommand(string $promptFile, string $model): array
    {
        return ['claude', '-p', '@'.$promptFile, '--model', $model, '--output-format', 'json'];
    }

    public function parseOutput(string $output): LlmResponse
    {
        $trimmed = mb_trim($output);

        // Claude CLI with --output-format json returns {result: "...", ...}
        $decoded = json_decode($trimmed, true);

        if (is_array($decoded) && isset($decoded['result']) && is_string($decoded['result'])) {
            return new LlmResponse(text: $decoded['result']);
        }

        return new LlmResponse(text: $trimmed);
    }

    public function name(): string
    {
        return 'claude';
    }
}

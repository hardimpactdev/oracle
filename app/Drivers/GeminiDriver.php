<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Data\LlmResponse;

final readonly class GeminiDriver implements LlmDriver
{
    public function buildCommand(string $promptFile, string $model): array
    {
        return ['gemini', '--model', $model, '-f', $promptFile, '-o', 'json', '--yolo'];
    }

    public function parseOutput(string $output): LlmResponse
    {
        $trimmed = mb_trim($output);

        // Gemini CLI `-o json` wraps output in an envelope: {session_id, response, stats}
        $envelope = json_decode($trimmed, true);

        if (is_array($envelope) && isset($envelope['response']) && is_string($envelope['response'])) {
            return new LlmResponse(
                text: $envelope['response'],
                sessionId: $envelope['session_id'] ?? null,
            );
        }

        // Fallback: treat raw output as the response text
        return new LlmResponse(text: $trimmed);
    }

    public function name(): string
    {
        return 'gemini';
    }
}

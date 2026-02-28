<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Data\LlmResponse;

final readonly class GeminiDriver implements LlmDriver
{
    public function buildCommand(string $promptFile, string $model): array
    {
        // No --prompt flag: gemini CLI reads from STDIN (piped by WithLlmInvocation).
        return ['gemini', '--model', $model, '--output-format', 'json', '--yolo'];
    }

    public function parseOutput(string $output): LlmResponse
    {
        $trimmed = mb_trim($output);

        // gemini --output-format=json returns a JSON envelope.
        // Historically some versions returned {session_id, response, stats}.
        // Newer versions may return other keys; be defensive.
        $envelope = json_decode($trimmed, true);

        if (is_array($envelope)) {
            foreach (['response', 'output_text', 'text', 'content'] as $key) {
                if (isset($envelope[$key]) && is_string($envelope[$key]) && $envelope[$key] !== '') {
                    return new LlmResponse(
                        text: $envelope[$key],
                        sessionId: isset($envelope['session_id']) && is_string($envelope['session_id'])
                            ? $envelope['session_id']
                            : null,
                    );
                }
            }
        }

        return new LlmResponse(text: $trimmed);
    }

    public function name(): string
    {
        return 'gemini';
    }
}

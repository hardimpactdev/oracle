<?php

declare(strict_types=1);

namespace App\Services;

final class ResponseParser
{
    /**
     * Parse a response text into a JSON array.
     *
     * Handles: code fence stripping, JSON decoding, output.json fallback.
     */
    public function parseJson(string $text, ?string $workingDirectory = null): ?array
    {
        $cleaned = $this->stripCodeFences($text);

        $parsed = json_decode(mb_trim($cleaned), true);

        if (is_array($parsed)) {
            return $parsed;
        }

        // Fallback: check for output.json file in working directory (Gemini CLI may write it)
        if ($workingDirectory !== null) {
            $parsed = $this->readOutputFile($workingDirectory);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Strip markdown code fences from response text.
     *
     * Handles ```json, ```markdown, ```md, and plain ``` fences.
     */
    public function stripCodeFences(string $text): string
    {
        if (preg_match('/```(?:json|markdown|md)?\s*\n?(.*?)\n?\s*```/s', $text, $matches) === 1) {
            return $matches[1];
        }

        return $text;
    }

    /**
     * Read and parse output.json file from working directory.
     *
     * Gemini CLI sometimes writes structured output to this file.
     */
    private function readOutputFile(string $workingDirectory): ?array
    {
        $outputFile = $workingDirectory.'/output.json';

        if (! is_file($outputFile)) {
            return null;
        }

        $content = file_get_contents($outputFile);
        if ($content === false) {
            return null;
        }

        @unlink($outputFile);

        $parsed = json_decode(mb_trim($content), true);

        return is_array($parsed) ? $parsed : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Commands;

use App\Concerns\WithLlmInvocation;
use App\Services\ConfigManager;
use LaravelZero\Framework\Commands\Command;

/**
 * Thin LLM wrapper — reads a prompt from stdin, calls the configured LLM,
 * and writes the raw parsed JSON response to stdout.
 *
 * This is the unified interface used by Sequence (and other callers) so that
 * prompts stay in the caller while Oracle handles driver routing and auth.
 *
 * Usage:
 *   echo "$prompt" | oracle call --json
 *   echo "$prompt" | oracle call --json --driver claude --model claude-opus-4-5
 *   echo "$prompt" | oracle call --json --driver gemini --timeout 300
 */
final class CallCommand extends Command
{
    use WithLlmInvocation;

    protected $signature = 'call
        {--driver= : LLM driver to use (gemini, claude, codex)}
        {--model= : LLM model to use}
        {--timeout= : Timeout in seconds (default: 180)}
        {--input= : Prompt text (alternative to stdin; stdin is used when omitted)}
        {--json : Output errors as JSON (output is always JSON on success)}';

    protected $description = 'Call an LLM with a prompt from stdin and return the raw JSON response';

    public function handle(ConfigManager $config): int
    {
        /** @var string|null $inputOption */
        $inputOption = $this->option('input');

        $prompt = $inputOption !== null
            ? $inputOption
            : stream_get_contents(STDIN);

        if ($prompt === false || mb_trim($prompt) === '') {
            return $this->failCall('No prompt provided (use stdin or --input)');
        }

        /** @var string $driverName */
        $driverName = $this->option('driver')
            ?? $config->get('driver')
            ?? config('oracle.driver', 'gemini');

        /** @var string $model */
        $model = $this->option('model')
            ?? $config->get('model')
            ?? config('oracle.model', 'gemini-2.5-flash');

        $timeout = (int) ($this->option('timeout')
            ?? $config->get('timeout')
            ?? config('oracle.timeout', 180));

        $driver = match ($driverName) {
            'claude' => new \App\Drivers\ClaudeDriver,
            'codex' => new \App\Drivers\CodexDriver,
            default => new \App\Drivers\GeminiDriver,
        };

        try {
            $parsed = $this->invokeLlm($driver, $model, $prompt, (string) getcwd(), $timeout);
        } catch (\RuntimeException $e) {
            return $this->failCall($e->getMessage());
        }

        if ($parsed === null) {
            return $this->failCall('LLM returned non-JSON or unparseable response');
        }

        // Raw JSON output — no {success, data} wrapper
        $this->output->writeln(json_encode($parsed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function failCall(string $message): int
    {
        if ($this->option('json') !== false) {
            $this->output->getErrorOutput()->writeln(json_encode(['error' => $message], JSON_UNESCAPED_SLASHES));
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }
}

<?php

declare(strict_types=1);

use App\Data\LlmResponse;
use App\Drivers\ClaudeDriver;
use App\Drivers\CodexDriver;
use App\Drivers\GeminiDriver;

describe('GeminiDriver', function () {
    beforeEach(function () {
        $this->driver = new GeminiDriver;
    });

    it('has correct name', function () {
        expect($this->driver->name())->toBe('gemini');
    });

    it('builds correct command', function () {
        $command = $this->driver->buildCommand('/tmp/prompt.txt', 'gemini-2.5-flash');

        expect($command)->toBe([
            'gemini', '--model', 'gemini-2.5-flash', '--prompt', '', '--output-format', 'json', '--yolo',
        ]);
    });

    it('unwraps gemini envelope', function () {
        $output = json_encode([
            'session_id' => 'test-session',
            'response' => 'The actual response text',
            'stats' => ['tokens' => 100],
        ]);

        $result = $this->driver->parseOutput($output);

        expect($result)
            ->toBeInstanceOf(LlmResponse::class)
            ->text->toBe('The actual response text')
            ->sessionId->toBe('test-session');
    });

    it('falls back to raw output when no envelope', function () {
        $output = 'raw text output';

        $result = $this->driver->parseOutput($output);

        expect($result)
            ->toBeInstanceOf(LlmResponse::class)
            ->text->toBe('raw text output')
            ->sessionId->toBeNull();
    });
});

describe('ClaudeDriver', function () {
    beforeEach(function () {
        $this->driver = new ClaudeDriver;
    });

    it('has correct name', function () {
        expect($this->driver->name())->toBe('claude');
    });

    it('builds correct command', function () {
        $command = $this->driver->buildCommand('/tmp/prompt.txt', 'claude-sonnet-4-5-20250514');

        expect($command)->toBe([
            'claude', '-p', '@/tmp/prompt.txt', '--model', 'claude-sonnet-4-5-20250514', '--output-format', 'json',
        ]);
    });

    it('unwraps claude result envelope', function () {
        $output = json_encode([
            'result' => 'Extracted result text',
        ]);

        $result = $this->driver->parseOutput($output);

        expect($result)
            ->toBeInstanceOf(LlmResponse::class)
            ->text->toBe('Extracted result text');
    });

    it('falls back to raw output when no result key', function () {
        $result = $this->driver->parseOutput('plain text');

        expect($result->text)->toBe('plain text');
    });
});

describe('CodexDriver', function () {
    beforeEach(function () {
        $this->driver = new CodexDriver;
    });

    it('has correct name', function () {
        expect($this->driver->name())->toBe('codex');
    });

    it('builds correct command', function () {
        $promptFile = tempnam(sys_get_temp_dir(), 'oracle_test_');
        file_put_contents($promptFile, 'test prompt content');

        $command = $this->driver->buildCommand($promptFile, 'codex-mini-latest');

        expect($command)->toBe([
            'codex', '-p', 'test prompt content', '--model', 'codex-mini-latest',
        ]);

        @unlink($promptFile);
    });

    it('returns raw output', function () {
        $result = $this->driver->parseOutput('  raw output  ');

        expect($result)
            ->toBeInstanceOf(LlmResponse::class)
            ->text->toBe('raw output');
    });
});

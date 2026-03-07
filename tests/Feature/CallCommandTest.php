<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

describe('CallCommand', function (): void {
    it('calls the LLM and returns raw parsed JSON', function (): void {
        Process::fake([
            '*gemini*' => Process::result(output: json_encode([
                'response' => json_encode(['verdict' => 'pass', 'summary' => 'All good', 'confidence' => 0.9]),
            ])),
        ]);

        $this->artisan('call', [
            '--input' => 'Verify the implementation',
            '--json' => true,
        ])->assertSuccessful();
    });

    it('fails when no prompt is provided', function (): void {
        $this->artisan('call', ['--json' => true])
            ->assertFailed();
    });

    it('accepts --driver and --model flags', function (): void {
        Process::fake([
            '*claude*' => Process::result(output: json_encode([
                'result' => json_encode(['verdict' => 'approve', 'summary' => 'LGTM']),
            ])),
        ]);

        $this->artisan('call', [
            '--input' => 'Review this PR',
            '--driver' => 'claude',
            '--model' => 'claude-opus-4-5',
            '--json' => true,
        ])->assertSuccessful();
    });

    it('fails when LLM process exits non-zero', function (): void {
        Process::fake([
            '*gemini*' => Process::result(exitCode: 1, errorOutput: 'model overloaded'),
        ]);

        $this->artisan('call', [
            '--input' => 'Some prompt',
            '--json' => true,
        ])->assertFailed();
    });

    it('fails when LLM returns non-JSON text', function (): void {
        Process::fake([
            '*gemini*' => Process::result(output: 'This is plain text, not JSON'),
        ]);

        $this->artisan('call', [
            '--input' => 'Some prompt',
            '--json' => true,
        ])->assertFailed();
    });

    it('uses default gemini driver when no driver specified', function (): void {
        $ran = false;

        Process::fake(function ($process) use (&$ran) {
            if (str_contains(implode(' ', $process->command), 'gemini')) {
                $ran = true;
            }

            return Process::result(output: json_encode([
                'response' => json_encode(['steps' => [], 'summary' => 'done']),
            ]));
        });

        $this->artisan('call', [
            '--input' => 'Plan this task',
            '--json' => true,
        ])->assertSuccessful();

        expect($ran)->toBeTrue();
    });
});

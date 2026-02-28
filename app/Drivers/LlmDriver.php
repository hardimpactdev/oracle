<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Data\LlmResponse;

interface LlmDriver
{
    /**
     * Build the CLI command array for Process::run().
     *
     * @return array<string>
     */
    public function buildCommand(string $promptFile, string $model): array;

    /**
     * Parse raw CLI output into structured data.
     */
    public function parseOutput(string $output): LlmResponse;

    /**
     * Driver identifier.
     */
    public function name(): string;
}

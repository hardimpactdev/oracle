<?php

declare(strict_types=1);

namespace App\Data;

final readonly class LlmResponse
{
    public function __construct(
        public string $text,
        public ?string $sessionId = null,
    ) {}
}

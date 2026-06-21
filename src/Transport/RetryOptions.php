<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Transport;

final class RetryOptions
{
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $initialDelayMs = 200,
        public readonly int $maxDelayMs = 5000,
        public readonly float $multiplier = 2.0,
    ) {
    }
}

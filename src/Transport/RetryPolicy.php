<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Transport;

final class RetryPolicy
{
    /** @var callable():float */
    private $rand;

    public function __construct(private readonly RetryOptions $options, ?callable $rand = null)
    {
        $this->rand = $rand ?? static fn (): float => mt_rand() / mt_getrandmax();
    }

    public function shouldRetry(int $status): bool
    {
        return $status === 0 || $status >= 500 || $status === 429;
    }

    public function delayMs(int $attempt): int
    {
        $base = min(
            $this->options->maxDelayMs,
            (int) ($this->options->initialDelayMs * ($this->options->multiplier ** $attempt)),
        );
        $jitter = 0.5 + 0.5 * ($this->rand)();

        return (int) ($base * $jitter);
    }

    public function maxAttempts(): int
    {
        return $this->options->maxAttempts;
    }
}

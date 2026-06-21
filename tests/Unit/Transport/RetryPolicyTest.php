<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Transport;

use NewInstance\BugWatch\Transport\RetryOptions;
use NewInstance\BugWatch\Transport\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function test_should_retry_matrix(): void
    {
        $p = new RetryPolicy(new RetryOptions());
        self::assertTrue($p->shouldRetry(0));   // network error
        self::assertTrue($p->shouldRetry(500));
        self::assertTrue($p->shouldRetry(429));
        self::assertFalse($p->shouldRetry(200));
        self::assertFalse($p->shouldRetry(400));
        self::assertFalse($p->shouldRetry(401));
    }

    public function test_delay_grows_and_is_capped_with_jitter(): void
    {
        // rand fixed at 1.0 → jitter factor = 1.0 → delay == base
        $p = new RetryPolicy(new RetryOptions(initialDelayMs: 200, maxDelayMs: 5000, multiplier: 2.0), static fn () => 1.0);
        self::assertSame(200, $p->delayMs(0));
        self::assertSame(400, $p->delayMs(1));
        self::assertSame(800, $p->delayMs(2));
        self::assertSame(5000, $p->delayMs(10)); // capped
    }

    public function test_jitter_floor(): void
    {
        // rand fixed at 0.0 → jitter factor = 0.5
        $p = new RetryPolicy(new RetryOptions(initialDelayMs: 200), static fn () => 0.0);
        self::assertSame(100, $p->delayMs(0));
    }
}

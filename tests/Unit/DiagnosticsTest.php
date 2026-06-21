<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\Diagnostics;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTest extends TestCase
{
    public function test_counters_increment(): void
    {
        $d = new Diagnostics(false);
        $d->incr('dropped');
        $d->incr('dropped');
        $d->incr('retries');

        self::assertSame(['dropped' => 2, 'retries' => 1], $d->counters());
    }

    public function test_logging_is_silent_when_debug_off(): void
    {
        $d = new Diagnostics(false);
        // Must not throw and must not emit (no assertion on error_log output; just no exception).
        $d->error('x', new \RuntimeException('y'));
        $d->warn('w');
        self::assertSame([], $d->counters());
    }
}

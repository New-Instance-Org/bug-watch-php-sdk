<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\Severity;
use PHPUnit\Framework\TestCase;

final class SeverityTest extends TestCase
{
    public function test_psr3_names(): void
    {
        self::assertSame(20, Severity::toNumber('debug'));
        self::assertSame(30, Severity::toNumber('info'));
        self::assertSame(30, Severity::toNumber('notice'));
        self::assertSame(40, Severity::toNumber('warning'));
        self::assertSame(50, Severity::toNumber('error'));
        self::assertSame(60, Severity::toNumber('critical'));
        self::assertSame(60, Severity::toNumber('alert'));
        self::assertSame(60, Severity::toNumber('emergency'));
    }

    public function test_monolog_ints(): void
    {
        self::assertSame(20, Severity::toNumber(100)); // DEBUG
        self::assertSame(30, Severity::toNumber(200)); // INFO
        self::assertSame(40, Severity::toNumber(300)); // WARNING
        self::assertSame(50, Severity::toNumber(400)); // ERROR
        self::assertSame(60, Severity::toNumber(500)); // CRITICAL
        self::assertSame(60, Severity::toNumber(600)); // EMERGENCY
    }

    public function test_bugwatch_numeric_and_fallback(): void
    {
        self::assertSame(50, Severity::toNumber(50));
        self::assertSame(30, Severity::toNumber('not-a-level'));
        self::assertSame(40, Severity::toNumber('40'));
    }
}

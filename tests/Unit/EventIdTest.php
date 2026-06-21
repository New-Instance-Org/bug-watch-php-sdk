<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\EventId;
use PHPUnit\Framework\TestCase;

final class EventIdTest extends TestCase
{
    public function test_format_and_uniqueness(): void
    {
        $a = EventId::generate();
        $b = EventId::generate();

        self::assertMatchesRegularExpression('/^bw_e_[0-9a-f]{32}$/', $a);
        self::assertNotSame($a, $b);
    }
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\Sdk;
use PHPUnit\Framework\TestCase;

final class SdkTest extends TestCase
{
    public function test_sdk_identity(): void
    {
        self::assertSame('newinstance/bugwatch-php', Sdk::NAME);
        self::assertSame('0.1.0', Sdk::VERSION);
    }
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;

final class ExceptionCaptureTest extends TestCase
{
    public function test_reported_exception_is_captured(): void
    {
        config()->set('bugwatch.capture_exceptions', true);

        app(ExceptionHandler::class)->report(new \RuntimeException('kaboom'));
        app(\NewInstance\BugWatch\Client::class)->flush();

        self::assertNotEmpty($this->transport->events);
        self::assertSame(\RuntimeException::class, $this->transport->events[0]['exception']['type']);
    }
}

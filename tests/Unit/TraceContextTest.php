<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use NewInstance\BugWatch\TraceContext;
use PHPUnit\Framework\TestCase;

final class TraceContextTest extends TestCase
{
    private const TRACE = '4bf92f3577b34da6a3ce929d0e0e4736';
    private const SPAN = '00f067aa0ba902b7';

    public function testParsesValidTraceparent(): void
    {
        $parsed = TraceContext::parseTraceparent('00-' . self::TRACE . '-' . self::SPAN . '-01');
        self::assertSame(['traceId' => self::TRACE, 'spanId' => self::SPAN], $parsed);
    }

    public function testRejectsInvalidTraceparent(): void
    {
        self::assertNull(TraceContext::parseTraceparent(null));
        self::assertNull(TraceContext::parseTraceparent('garbage'));
        self::assertNull(TraceContext::parseTraceparent('00-' . str_repeat('0', 32) . '-' . self::SPAN . '-01'));
    }

    public function testBuildRoundTrips(): void
    {
        $header = TraceContext::buildTraceparent(strtoupper(self::TRACE), self::SPAN);
        self::assertSame('00-' . self::TRACE . '-' . self::SPAN . '-01', $header);
        self::assertNull(TraceContext::buildTraceparent('bad', self::SPAN));
    }

    public function testEventCarriesScopeTraceContext(): void
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);
        $client->setTraceContext(self::TRACE, self::SPAN);
        $client->captureMessage('traced');
        $client->flush();
        $event = $transport->events[0];
        self::assertSame(self::TRACE, $event['traceId']);
        self::assertSame(self::SPAN, $event['spanId']);
    }

    public function testCaptureExceptionHintOverridesScope(): void
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);
        $client->setTraceContext(self::TRACE, self::SPAN);
        $other = 'aaaa2f3577b34da6a3ce929d0e0e4736';
        $client->captureException(new \RuntimeException('x'), ['traceId' => $other, 'spanId' => '11f067aa0ba902b7']);
        $client->flush();
        $event = $transport->events[0];
        self::assertSame($other, $event['traceId']);
        self::assertSame('11f067aa0ba902b7', $event['spanId']);
    }

    public function testInvalidIdsAreDropped(): void
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);
        $client->setTraceContext('not-hex', 'nope');
        $client->captureMessage('untraced');
        $client->flush();
        self::assertArrayNotHasKey('traceId', $transport->events[0]);
    }
}

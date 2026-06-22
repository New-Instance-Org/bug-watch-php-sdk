<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\BugWatch;
use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Logger\Logger;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class BugWatchTest extends TestCase
{
    private InMemoryTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $this->transport);
        (new \ReflectionProperty(BugWatch::class, 'client'))->setValue(null, $client);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(BugWatch::class, 'client'))->setValue(null, null);
    }

    public function test_facade_delegates_scope_and_capture(): void
    {
        BugWatch::setRelease('app@2.0.0');
        BugWatch::setTags(['region' => 'eu', 'plan' => 'pro']);
        BugWatch::setContext('payment', ['provider' => 'paystack']);
        BugWatch::setUser(['id' => 'u1', 'email' => 'a@b.com']);
        $id = BugWatch::captureMessage('hello', 'error');
        BugWatch::flush();

        self::assertStringStartsWith('bw_e_', $id);
        self::assertCount(1, $this->transport->events);
        $event = $this->transport->events[0];
        self::assertSame('app@2.0.0', $event['release']);
        self::assertSame('eu', $event['tags']['region']);
        self::assertSame(['id' => 'u1', 'email' => 'a@b.com'], $event['user']);
        self::assertSame(50, $event['level']);
        self::assertSame(['provider' => 'paystack'], $event['contexts']['payment']);
    }

    public function test_facade_withScope_is_isolated(): void
    {
        BugWatch::setTag('a', '1');
        BugWatch::withScope(function ($scope): void {
            $scope->fingerprint = 'custom-key';
            BugWatch::captureMessage('inner');
        });
        BugWatch::captureMessage('outer');
        BugWatch::flush();

        self::assertSame('custom-key', $this->transport->events[0]['fingerprint']);
        self::assertArrayNotHasKey('fingerprint', $this->transport->events[1]); // popped after withScope
    }

    public function test_facade_getLogger_returns_psr3_logger(): void
    {
        self::assertInstanceOf(Logger::class, BugWatch::getLogger());
        self::assertInstanceOf(\Psr\Log\LoggerInterface::class, BugWatch::getLogger());
    }
}

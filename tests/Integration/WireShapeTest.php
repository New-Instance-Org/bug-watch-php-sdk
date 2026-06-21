<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Integration;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class WireShapeTest extends TestCase
{
    public function test_end_to_end_event_matches_wire_schema(): void
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray([
            'projectKey' => 'sk_test_abc:secret',
            'release' => 'checkout@2.4.1',
        ]), $transport);

        $client->setUser(['id' => 'u_123', 'email' => 'alice@acme.com', 'password' => 'secret']);
        $client->setTag('region', 'eu-west-1');

        $client->captureException(
            new \RuntimeException('outer', 0, new \LogicException('inner')),
            ['tags' => ['route' => 'POST /checkout']],
        );
        $client->flush();

        self::assertCount(1, $transport->events);
        $e = $transport->events[0];

        // identity / timing / severity
        self::assertMatchesRegularExpression('/^bw_e_[0-9a-f]{32}$/', $e['eventId']);
        self::assertIsInt($e['time']);
        self::assertSame(50, $e['level']);
        self::assertSame('outer', $e['message']);

        // exception + causes
        self::assertSame(\RuntimeException::class, $e['exception']['type']);
        self::assertTrue($e['exception']['stacktrace'][0]['in_app']);
        self::assertSame('inner', $e['causes'][0]['value']);

        // rich context (input tags merge over scope tags)
        self::assertSame('checkout@2.4.1', $e['release']);
        self::assertSame(['region' => 'eu-west-1', 'route' => 'POST /checkout'], $e['tags']);

        // user allowlist + redaction
        self::assertSame(['id' => 'u_123', 'email' => 'alice@acme.com'], $e['user']);
        self::assertArrayNotHasKey('password', $e['user']);

        // provenance + no environment on the wire
        self::assertSame(['name' => 'newinstance/bugwatch-php', 'version' => '0.1.0'], $e['sdk']);
        self::assertArrayNotHasKey('environment', $e);
    }
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Logger;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Logger\Logger;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private function setup2(): array
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);

        return [new Logger($client), $client, $transport];
    }

    public function test_psr3_levels_map_and_placeholders_interpolate(): void
    {
        [$logger, $client, $transport] = $this->setup2();
        $logger->warning('hello {name}', ['name' => 'world', 'region' => 'eu']);
        $client->flush();

        self::assertSame(40, $transport->events[0]['level']);
        self::assertSame('hello world', $transport->events[0]['message']);
        self::assertSame('eu', $transport->events[0]['tags']['region']);
    }

    public function test_exception_in_context_is_captured(): void
    {
        [$logger, $client, $transport] = $this->setup2();
        $logger->error('failed', ['exception' => new \RuntimeException('boom')]);
        $client->flush();

        self::assertSame(\RuntimeException::class, $transport->events[0]['exception']['type']);
        self::assertSame(50, $transport->events[0]['level']);
    }

    public function test_implements_psr3(): void
    {
        [$logger] = $this->setup2();
        self::assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }
}

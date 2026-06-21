<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Integration\Monolog;

use Monolog\Logger;
use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Integration\Monolog\Handler;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class HandlerTest extends TestCase
{
    private function wire(): array
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);
        $logger = new Logger('app');
        $logger->pushHandler(new Handler($client, 'debug'));

        return [$logger, $client, $transport];
    }

    public function test_log_record_becomes_captureLog(): void
    {
        [$logger, $client, $transport] = $this->wire();
        $logger->warning('cache miss {key}', ['key' => 'u:1', 'region' => 'eu']);
        $client->flush();

        self::assertCount(1, $transport->events);
        self::assertSame(40, $transport->events[0]['level']);              // WARNING -> 40
        self::assertSame('cache miss {key}', $transport->events[0]['message']); // Monolog leaves placeholder; BugWatch stores raw
        self::assertSame('eu', $transport->events[0]['tags']['region']);
        self::assertSame('app', $transport->events[0]['tags']['channel']);
    }

    public function test_exception_in_context_becomes_captureException(): void
    {
        [$logger, $client, $transport] = $this->wire();
        $logger->error('failed', ['exception' => new \RuntimeException('boom')]);
        $client->flush();

        self::assertSame(\RuntimeException::class, $transport->events[0]['exception']['type']);
        self::assertSame(50, $transport->events[0]['level']);
    }

    public function test_handler_never_throws_into_monolog(): void
    {
        // A client whose transport fails must not cause logging to throw.
        $transport = new InMemoryTransport();
        $transport->result = false;
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);
        $logger = new Logger('app');
        $logger->pushHandler(new Handler($client));

        $logger->error('still fine'); // must not throw
        self::assertTrue(true);
    }
}

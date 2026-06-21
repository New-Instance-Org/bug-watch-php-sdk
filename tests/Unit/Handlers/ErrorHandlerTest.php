<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Handlers;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Handlers\ErrorHandler;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the singleton between tests so each wire() gets a fresh client/transport.
        $ref = new \ReflectionProperty(ErrorHandler::class, 'instance');
        $ref->setValue(null, null);
    }

    private function wire(): array
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);

        return [ErrorHandler::install($client, ['errors' => false, 'exceptions' => false, 'shutdown' => false]), $client, $transport];
    }

    public function test_handle_exception_captures_fatal(): void
    {
        [$h, $client, $transport] = $this->wire();
        $h->handleException(new \RuntimeException('uncaught'));
        $client->flush();

        self::assertSame(\RuntimeException::class, $transport->events[0]['exception']['type']);
        self::assertSame(60, $transport->events[0]['level']); // fatal
    }

    public function test_handle_error_maps_level_and_chains(): void
    {
        [$h, $client, $transport] = $this->wire();
        $old = error_reporting(E_ALL);
        $ret = $h->handleError(E_USER_WARNING, 'careful', '/a.php', 10);
        error_reporting($old);
        $client->flush();

        self::assertFalse($ret); // returns false so PHP's normal handler still runs
        self::assertSame(40, $transport->events[0]['level']); // warning
        self::assertSame('careful', $transport->events[0]['message']);
        self::assertSame('10', $transport->events[0]['tags']['line']);
    }

    public function test_handle_error_respects_error_reporting(): void
    {
        [$h, $client, $transport] = $this->wire();
        $old = error_reporting(0); // suppress all
        $ret = $h->handleError(E_WARNING, 'suppressed', '/a.php', 1);
        error_reporting($old);
        $client->flush();

        self::assertFalse($ret);
        self::assertCount(0, $transport->events); // nothing captured when suppressed
    }

    public function test_install_is_idempotent_and_returns_same_instance(): void
    {
        [$a] = $this->wire();
        $b = ErrorHandler::install(new Client(Config::fromArray(['enabled' => false])), ['errors' => false, 'exceptions' => false, 'shutdown' => false]);
        self::assertSame($a, $b);
    }
}

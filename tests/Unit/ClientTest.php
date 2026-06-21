<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function client(array $opts = []): array
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s'] + $opts), $transport);

        return [$client, $transport];
    }

    public function test_capture_message_flushes_to_transport(): void
    {
        [$client, $transport] = $this->client();
        $id = $client->captureMessage('hi', 'error');
        $client->flush();

        self::assertStringStartsWith('bw_e_', $id);
        self::assertCount(1, $transport->events);
        self::assertSame(50, $transport->events[0]['level']);
        self::assertSame($id, $transport->events[0]['eventId']);
    }

    public function test_scope_user_and_tags_attach(): void
    {
        [$client, $transport] = $this->client();
        $client->setUser(['id' => 'u1', 'email' => 'a@b.com']);
        $client->setTag('region', 'eu');
        $client->captureMessage('m');
        $client->flush();

        self::assertSame(['id' => 'u1', 'email' => 'a@b.com'], $transport->events[0]['user']);
        self::assertSame(['region' => 'eu'], $transport->events[0]['tags']);
    }

    public function test_with_scope_is_isolated(): void
    {
        [$client, $transport] = $this->client();
        $client->setTag('a', '1');
        $client->withScope(function ($scope) use ($client): void {
            $scope->tags['b'] = '2';
            $client->captureMessage('inner');
        });
        $client->captureMessage('outer');
        $client->flush();

        self::assertSame(['a' => '1', 'b' => '2'], $transport->events[0]['tags']);
        self::assertSame(['a' => '1'], $transport->events[1]['tags']);
    }

    public function test_double_capture_of_same_throwable_is_deduped(): void
    {
        [$client, $transport] = $this->client();
        $e = new \RuntimeException('boom');
        $id1 = $client->captureException($e);
        $id2 = $client->captureException($e);
        $client->flush();

        self::assertSame($id1, $id2);
        self::assertCount(1, $transport->events);
    }

    public function test_sampling_zero_drops_everything(): void
    {
        [$client, $transport] = $this->client(['sampleRate' => 0.0]);
        $client->captureMessage('m');
        $client->flush();
        self::assertCount(0, $transport->events);
    }

    public function test_disabled_client_is_noop(): void
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['enabled' => false]), $transport);
        $client->captureMessage('m');
        $client->flush();
        self::assertCount(0, $transport->events);
    }

    public function test_capture_never_throws_when_transport_fails(): void
    {
        $transport = new InMemoryTransport();
        $transport->result = false;
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);

        $id = $client->captureMessage('m');
        self::assertFalse($client->flush()); // reports failure, but does not throw
        self::assertNotSame('', $id);
    }

    public function test_with_scope_pops_and_does_not_escape_on_throw(): void
    {
        [$client, $transport] = $this->client();
        $client->setTag('a', '1');
        $client->withScope(function ($scope): void {
            $scope->tags['b'] = '2';
            throw new \RuntimeException('boom');
        });
        // No exception escaped (guarded) AND the scope was popped → outer tags intact.
        $client->captureMessage('after');
        $client->flush();
        self::assertSame(['a' => '1'], $transport->events[0]['tags']);
    }

    public function test_sampled_out_capture_still_returns_event_id(): void
    {
        [$client] = $this->client(['sampleRate' => 0.0]);
        $id = $client->captureMessage('m');
        self::assertStringStartsWith('bw_e_', $id);
    }
}

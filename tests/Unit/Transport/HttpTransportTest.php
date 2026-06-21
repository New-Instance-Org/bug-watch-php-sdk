<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Transport;

use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Transport\HttpTransport;
use NewInstance\BugWatch\Transport\RetryOptions;
use NewInstance\BugWatch\Transport\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class HttpTransportTest extends TestCase
{
    private function config(): Config
    {
        return Config::fromArray(['projectKey' => 'sk_test_abc:secret']);
    }

    public function test_posts_ndjson_with_auth_header(): void
    {
        $captured = [];
        $sender = function (string $body, array $headers, string $url) use (&$captured): array {
            $captured = compact('body', 'headers', 'url');

            return ['status' => 202];
        };

        $t = new HttpTransport($this->config(), new RetryPolicy(new RetryOptions()), $sender, static fn () => null);
        $ok = $t->send([['eventId' => 'a', 'level' => 50], ['eventId' => 'b', 'level' => 30]]);

        self::assertTrue($ok);
        self::assertSame('https://api.newinstance.cloud/api/v1/bugwatch/ingest', $captured['url']);
        self::assertContains('Content-Type: application/x-ndjson', $captured['headers']);
        self::assertContains('x-api-key: sk_test_abc:secret', $captured['headers']);
        $lines = explode("\n", $captured['body']);
        self::assertCount(2, $lines);
        self::assertSame(['eventId' => 'a', 'level' => 50], json_decode($lines[0], true));
    }

    public function test_retries_on_500_then_succeeds(): void
    {
        $calls = 0;
        $sender = function () use (&$calls): array {
            $calls++;

            return ['status' => $calls < 3 ? 500 : 202];
        };

        $t = new HttpTransport($this->config(), new RetryPolicy(new RetryOptions(maxAttempts: 3), static fn () => 0.0), $sender, static fn () => null);
        self::assertTrue($t->send([['eventId' => 'a']]));
        self::assertSame(3, $calls);
    }

    public function test_does_not_retry_on_401(): void
    {
        $calls = 0;
        $sender = function () use (&$calls): array {
            $calls++;

            return ['status' => 401];
        };

        $t = new HttpTransport($this->config(), new RetryPolicy(new RetryOptions(maxAttempts: 3)), $sender, static fn () => null);
        self::assertFalse($t->send([['eventId' => 'a']]));
        self::assertSame(1, $calls);
    }

    public function test_network_exception_is_caught_and_retried(): void
    {
        $calls = 0;
        $sender = function () use (&$calls): array {
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('connection refused');
            }

            return ['status' => 202];
        };

        $t = new HttpTransport($this->config(), new RetryPolicy(new RetryOptions(maxAttempts: 3), static fn () => 0.0), $sender, static fn () => null);
        self::assertTrue($t->send([['eventId' => 'a']]));
        self::assertSame(2, $calls);
    }

    public function test_empty_batch_is_noop(): void
    {
        $t = new HttpTransport($this->config(), new RetryPolicy(new RetryOptions()), static fn () => ['status' => 500], static fn () => null);
        self::assertTrue($t->send([]));
    }

    public function test_psr18_client_path(): void
    {
        $mock = new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(202)]);
        $guzzle = new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($mock)]);
        $config = Config::fromArray(['projectKey' => 'k:s', 'httpClient' => $guzzle]);

        $t = new HttpTransport($config, new RetryPolicy(new RetryOptions()), null, static fn () => null);
        self::assertTrue($t->send([['eventId' => 'a']]));
    }
}

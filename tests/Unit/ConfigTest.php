<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Exception\ConfigException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_defaults(): void
    {
        $c = Config::fromArray(['projectKey' => 'sk_test_abc:secret']);
        self::assertSame('https://api.newinstance.cloud', $c->endpoint);
        self::assertTrue($c->enabled);
        self::assertSame(1.0, $c->sampleRate);
        self::assertSame(50, $c->batchSize);
        self::assertSame(1000, $c->maxQueueSize);
        self::assertSame(3, $c->retry->maxAttempts);
    }

    public function test_requires_key_or_session_url(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromArray([]);
    }

    public function test_disabled_does_not_require_key(): void
    {
        $c = Config::fromArray(['enabled' => false]);
        self::assertFalse($c->enabled);
    }

    public function test_rejects_bad_sample_rate(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromArray(['projectKey' => 'k:s', 'sampleRate' => 2.0]);
    }

    public function test_rejects_bad_endpoint(): void
    {
        $this->expectException(ConfigException::class);
        Config::fromArray(['projectKey' => 'k:s', 'endpoint' => 'not-a-url']);
    }
}

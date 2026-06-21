<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Session;

use NewInstance\BugWatch\Session\BrowserSession;
use PHPUnit\Framework\TestCase;

final class BrowserSessionTest extends TestCase
{
    public function test_mint_posts_with_api_key_and_returns_token(): void
    {
        $captured = [];
        $sender = function (string $url, array $headers) use (&$captured): array {
            $captured = compact('url', 'headers');

            return ['status' => 200, 'body' => json_encode(['token' => 't.sig', 'expiresAt' => 123])];
        };

        $out = BrowserSession::mint('sk_test_a:secret', 'https://api.newinstance.cloud', $sender);

        self::assertSame('https://api.newinstance.cloud/api/v1/bugwatch/browser-session', $captured['url']);
        self::assertContains('x-api-key: sk_test_a:secret', $captured['headers']);
        self::assertSame(['token' => 't.sig', 'expiresAt' => 123], $out);
    }

    public function test_mint_throws_on_non_200(): void
    {
        $sender = static fn (): array => ['status' => 401, 'body' => '{"error":"unauthorized"}'];
        $this->expectException(\RuntimeException::class);
        BrowserSession::mint('k:s', 'https://api.newinstance.cloud', $sender);
    }
}

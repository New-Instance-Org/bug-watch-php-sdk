<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use NewInstance\BugWatch\Laravel\Http\BrowserSessionController;

final class BrowserSessionControllerTest extends TestCase
{
    public function test_controller_returns_minted_session(): void
    {
        $controller = new BrowserSessionController();
        // Inject a fake minter so the test does no network.
        $controller->minter = static fn (array $o): array => ['token' => 't.sig', 'expiresAt' => 123];

        $response = $controller->__invoke();
        self::assertSame(['token' => 't.sig', 'expiresAt' => 123], $response->getData(true));
    }
}

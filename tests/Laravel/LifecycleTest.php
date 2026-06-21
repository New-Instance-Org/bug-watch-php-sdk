<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use NewInstance\BugWatch\Client;

final class LifecycleTest extends TestCase
{
    public function test_terminating_flushes_queued_events(): void
    {
        $client = app(Client::class);
        $client->captureMessage('queued before terminate');
        // Simulate the app terminating callback the provider registers.
        $this->app->terminate();

        self::assertCount(1, $this->transport->events); // flushed by the terminating hook
    }
}

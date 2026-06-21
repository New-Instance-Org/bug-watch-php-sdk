<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Laravel\BugWatchServiceProvider;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected InMemoryTransport $transport;

    protected function getPackageProviders($app): array
    {
        return [BugWatchServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bugwatch.key', 'sk_test_x:secret');
        $app['config']->set('bugwatch.endpoint', 'https://api.newinstance.cloud');
        // Swap the singleton for one backed by an in-memory transport so tests are hermetic.
        $this->transport = new InMemoryTransport();
        $app->singleton(Client::class, fn () => new Client(
            \NewInstance\BugWatch\Config::fromArray(['projectKey' => 'sk_test_x:secret']),
            $this->transport,
        ));
    }
}

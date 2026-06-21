<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use NewInstance\BugWatch\Client;
use Orchestra\Testbench\TestCase as Orchestra;

final class LiveE2eTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [\NewInstance\BugWatch\Laravel\BugWatchServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bugwatch.key', getenv('BUGWATCH_KEY') ?: 'x:y');
        $app['config']->set('bugwatch.endpoint', getenv('BUGWATCH_ENDPOINT') ?: 'http://localhost:5050');
        $app['config']->set('bugwatch.release', 'php-e2e-laravel@1.0.0');
    }

    public function test_live_channel_and_exception(): void
    {
        if (getenv('BUGWATCH_LIVE') !== '1') {
            self::markTestSkipped('live E2E (set BUGWATCH_LIVE=1 + BUGWATCH_KEY + BUGWATCH_ENDPOINT)');
        }
        $marker = getenv('BUGWATCH_MARKER') ?: 'laravel';
        config()->set('logging.channels.bugwatch', ['driver' => 'bugwatch']);

        Log::channel('bugwatch')->error('laravel channel e2e', ['e2e_marker' => $marker, 'token' => 'leak']);
        app(ExceptionHandler::class)->report(new \RuntimeException('laravel exception e2e ' . $marker));

        $ok = app(Client::class)->flush();
        self::assertTrue($ok); // all batches accepted (2xx) by the live ingest
    }
}

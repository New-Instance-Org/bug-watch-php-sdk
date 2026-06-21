<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use Illuminate\Http\Request;
use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Laravel\BugWatchContextMiddleware;

final class ContextMiddlewareTest extends TestCase
{
    public function test_middleware_attaches_request_context(): void
    {
        $client = app(Client::class);
        $mw = new BugWatchContextMiddleware($client);

        $request = Request::create('/checkout', 'POST');
        $mw->handle($request, function () use ($client) {
            $client->captureMessage('inside request');

            return response('ok');
        });
        $client->flush();

        $tags = $this->transport->events[0]['tags'];
        self::assertSame('POST', $tags['method']);
        self::assertSame('/checkout', $tags['url']);
    }
}

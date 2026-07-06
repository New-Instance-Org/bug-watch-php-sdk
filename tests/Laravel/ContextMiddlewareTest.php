<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Laravel\BugWatchContextMiddleware;

final class ContextMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        BugWatchContextMiddleware::forgetUserResolver();
        parent::tearDown();
    }

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

    public function test_default_attaches_id_only_from_request_user(): void
    {
        $client = app(Client::class);
        $mw = new BugWatchContextMiddleware($client);

        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn () => new GenericUser(['id' => 'u_default']));
        $mw->handle($request, function () use ($client) {
            $client->captureMessage('inside');

            return response('ok');
        });
        $client->flush();

        self::assertSame(['id' => 'u_default'], $this->transport->events[0]['user']);
    }

    public function test_custom_resolver_attaches_a_full_user(): void
    {
        BugWatchContextMiddleware::resolveUserUsing(
            fn (Request $r) => ['id' => 'u_custom', 'email' => 'carol@x.com'],
        );
        $client = app(Client::class);
        $mw = new BugWatchContextMiddleware($client);

        $request = Request::create('/checkout', 'POST');
        $mw->handle($request, function () use ($client) {
            $client->captureMessage('inside');

            return response('ok');
        });
        $client->flush();

        $user = $this->transport->events[0]['user'];
        self::assertSame('u_custom', $user['id']);
        self::assertSame('carol@x.com', $user['email']);
    }

    public function test_custom_resolver_returning_null_attaches_no_user(): void
    {
        BugWatchContextMiddleware::resolveUserUsing(fn (Request $r) => null);
        $client = app(Client::class);
        $mw = new BugWatchContextMiddleware($client);

        $request = Request::create('/checkout', 'POST');
        $request->setUserResolver(fn () => new GenericUser(['id' => 'ignored']));
        $mw->handle($request, function () use ($client) {
            $client->captureMessage('inside');

            return response('ok');
        });
        $client->flush();

        self::assertSame([], $this->transport->events[0]['user'] ?? []);
    }

    public function test_throwing_resolver_never_breaks_the_request(): void
    {
        BugWatchContextMiddleware::resolveUserUsing(function (Request $r): array {
            throw new \RuntimeException('auth blew up');
        });
        $client = app(Client::class);
        $mw = new BugWatchContextMiddleware($client);

        $request = Request::create('/checkout', 'POST');
        $response = $mw->handle($request, fn () => response('ok'));

        self::assertSame('ok', $response->getContent());
    }
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Laravel;

use Closure;
use Illuminate\Http\Request;
use NewInstance\BugWatch\Client;

final class BugWatchContextMiddleware
{
    public function __construct(private readonly Client $client)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $this->client->setTag('method', $request->method());
            $this->client->setTag('url', $request->path() === '/' ? '/' : '/' . ltrim($request->path(), '/'));
            $route = $request->route();
            if (is_object($route) && method_exists($route, 'getName') && is_string($route->getName())) {
                $this->client->setTag('route', $route->getName());
            }
            $user = $request->user();
            if ($user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
                $userId = $user->getAuthIdentifier();
                if (is_string($userId) || is_int($userId)) {
                    $this->client->setUser(['id' => (string) $userId]);
                }
            }
        } catch (\Throwable) {
            // context enrichment must never break the request
        }

        return $next($request);
    }

    public function terminate(Request $request, mixed $response): void
    {
        try {
            $this->client->flush();
            $this->client->resetScope();
        } catch (\Throwable) {
        }
    }
}

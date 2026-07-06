<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Laravel;

use Closure;
use Illuminate\Http\Request;
use NewInstance\BugWatch\Client;

final class BugWatchContextMiddleware
{
    private static ?Closure $userResolver = null;

    public function __construct(private readonly Client $client)
    {
    }

    /**
     * Customise how the request user is attached to captured events. The closure receives the
     * current Request and returns the user array (e.g. ['id' => ..., 'email' => ...]) or null for
     * an anonymous request. Register it once from a service provider's boot(). When unset, the
     * middleware falls back to the default guard's id.
     *
     * @param Closure(Request): (array<string,mixed>|null) $resolver
     */
    public static function resolveUserUsing(Closure $resolver): void
    {
        self::$userResolver = $resolver;
    }

    public static function forgetUserResolver(): void
    {
        self::$userResolver = null;
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
            $user = self::resolveUser($request);
            if ($user !== null) {
                $this->client->setUser($user);
            }
        } catch (\Throwable) {
            // context enrichment must never break the request
        }

        return $next($request);
    }

    /** @return array<string,mixed>|null */
    private static function resolveUser(Request $request): ?array
    {
        if (self::$userResolver !== null) {
            try {
                $resolved = (self::$userResolver)($request);
            } catch (\Throwable) {
                return null;
            }

            return is_array($resolved) ? $resolved : null;
        }

        $user = $request->user();
        if ($user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            $userId = $user->getAuthIdentifier();
            if (is_string($userId) || is_int($userId)) {
                return ['id' => (string) $userId];
            }
        }

        return null;
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

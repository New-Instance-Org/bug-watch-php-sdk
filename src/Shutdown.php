<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

final class Shutdown
{
    private static bool $registered = false;

    public static function register(Client $client): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        register_shutdown_function(static function () use ($client): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                $client->flush();
            } catch (\Throwable) {
                // never let shutdown raise
            }
        });
    }
}

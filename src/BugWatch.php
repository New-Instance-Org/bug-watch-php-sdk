<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

use NewInstance\BugWatch\Exception\ConfigException;
use NewInstance\BugWatch\Logger\Logger;

final class BugWatch
{
    private static ?Client $client = null;

    /** @param array<string,mixed> $options */
    public static function init(array $options): Client
    {
        try {
            self::$client = new Client(Config::fromArray($options));
        } catch (ConfigException $e) {
            if (($options['debug'] ?? false) === true) {
                throw $e;
            }
            error_log('[BugWatch] disabled: ' . $e->getMessage());
            self::$client = new Client(Config::fromArray(['enabled' => false]));
        }

        Shutdown::register(self::$client);

        return self::$client;
    }

    public static function client(): Client
    {
        return self::$client ??= self::init(['enabled' => false]);
    }

    /** @param array<string,mixed> $hint */
    public static function captureException(\Throwable $e, array $hint = []): string
    {
        return self::client()->captureException($e, $hint);
    }

    public static function captureMessage(string $message, int|string $level = 'info'): string
    {
        return self::client()->captureMessage($message, $level);
    }

    /** @param array<string,mixed> $input */
    public static function captureLog(array $input): string
    {
        return self::client()->captureLog($input);
    }

    /** @param array<string,mixed>|null $user */
    public static function setUser(?array $user): void
    {
        self::client()->setUser($user);
    }

    public static function setTag(string $key, mixed $value): void
    {
        self::client()->setTag($key, $value);
    }

    /** @param array<string,mixed> $kv */
    public static function setTags(array $kv): void
    {
        self::client()->setTags($kv);
    }

    public static function setContext(string $key, mixed $data): void
    {
        self::client()->setContext($key, $data);
    }

    public static function setRelease(string $release): void
    {
        self::client()->setRelease($release);
    }

    /** @param string|array<int|string,mixed> $fingerprint */
    public static function setFingerprint(string|array $fingerprint): void
    {
        self::client()->setFingerprint($fingerprint);
    }

    public static function withScope(callable $callback): void
    {
        self::client()->withScope($callback);
    }

    /** Clears per-request scope (use at long-running worker/request boundaries). */
    public static function resetScope(): void
    {
        self::client()->resetScope();
    }

    /** The native PSR-3 logger bound to the singleton client. */
    public static function getLogger(): Logger
    {
        return self::client()->getLogger();
    }

    public static function flush(): bool
    {
        return self::client()->flush();
    }

    public static function close(): void
    {
        self::client()->close();
    }
}

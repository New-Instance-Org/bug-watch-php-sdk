<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

use NewInstance\BugWatch\Exception\ConfigException;

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

    public static function flush(): bool
    {
        return self::client()->flush();
    }
}

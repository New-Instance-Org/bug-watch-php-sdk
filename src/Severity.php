<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

final class Severity
{
    public const TRACE = 10;
    public const DEBUG = 20;
    public const INFO = 30;
    public const WARN = 40;
    public const ERROR = 50;
    public const FATAL = 60;

    /** @var array<string,int> */
    private const NAMES = [
        'trace' => 10, 'debug' => 20, 'info' => 30, 'notice' => 30,
        'warn' => 40, 'warning' => 40, 'error' => 50, 'err' => 50,
        'critical' => 60, 'crit' => 60, 'alert' => 60, 'emergency' => 60, 'fatal' => 60,
    ];

    public static function toNumber(int|string $level): int
    {
        if (is_int($level)) {
            return $level >= 100 ? self::fromMonolog($level) : $level;
        }

        $key = strtolower(trim($level));
        if (isset(self::NAMES[$key])) {
            return self::NAMES[$key];
        }
        if (is_numeric($key)) {
            return (int) $key;
        }

        return self::INFO;
    }

    private static function fromMonolog(int $level): int
    {
        return match (true) {
            $level >= 500 => 60,
            $level >= 400 => 50,
            $level >= 300 => 40,
            default => $level >= 200 ? 30 : 20,
        };
    }
}

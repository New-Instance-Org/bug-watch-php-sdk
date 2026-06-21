<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

final class Diagnostics
{
    /** @var array<string,int> */
    private array $counters = [];

    public function __construct(private readonly bool $debug)
    {
    }

    public function error(string $message, ?\Throwable $e = null): void
    {
        $this->log('ERROR', $e === null ? $message : sprintf('%s (%s: %s)', $message, $e::class, $e->getMessage()));
    }

    public function warn(string $message): void
    {
        $this->log('WARN', $message);
    }

    public function info(string $message): void
    {
        $this->log('INFO', $message);
    }

    public function incr(string $key): void
    {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;
    }

    /** @return array<string,int> */
    public function counters(): array
    {
        return $this->counters;
    }

    private function log(string $level, string $message): void
    {
        if ($this->debug) {
            error_log(sprintf('[BugWatch %s] %s', $level, $message));
        }
    }
}

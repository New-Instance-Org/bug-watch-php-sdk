<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Queue;

final class EventQueue
{
    /** @var list<array<string,mixed>> */
    private array $items = [];
    private int $dropped = 0;

    public function __construct(private readonly int $max = 1000)
    {
    }

    /** @param array<string,mixed> $event */
    public function add(array $event): void
    {
        $this->items[] = $event;
        if (count($this->items) > $this->max) {
            array_shift($this->items);
            $this->dropped++;
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return list<array<string,mixed>> */
    public function drained(int $n): array
    {
        return array_splice($this->items, 0, max(0, $n));
    }

    public function dropped(): int
    {
        return $this->dropped;
    }
}

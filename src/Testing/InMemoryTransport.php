<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Testing;

use NewInstance\BugWatch\Transport\TransportInterface;

final class InMemoryTransport implements TransportInterface
{
    /** @var list<array<string,mixed>> */
    public array $events = [];
    public bool $result = true;

    public function send(array $events): bool
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }

        return $this->result;
    }
}

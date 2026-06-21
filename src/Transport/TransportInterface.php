<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Transport;

interface TransportInterface
{
    /** @param list<array<string,mixed>> $events */
    public function send(array $events): bool;
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Transport;

final class NullTransport implements TransportInterface
{
    public function send(array $events): bool
    {
        return true;
    }
}

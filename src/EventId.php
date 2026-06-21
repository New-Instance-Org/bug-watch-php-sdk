<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

final class EventId
{
    public static function generate(): string
    {
        return 'bw_e_' . bin2hex(random_bytes(16));
    }
}

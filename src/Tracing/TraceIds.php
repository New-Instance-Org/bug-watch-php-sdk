<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tracing;

final class TraceIds
{
    public static function generateTraceId(): string
    {
        do {
            $id = bin2hex(random_bytes(16));
        } while ($id === str_repeat('0', 32));

        return $id;
    }

    public static function generateSpanId(): string
    {
        do {
            $id = bin2hex(random_bytes(8));
        } while ($id === str_repeat('0', 16));

        return $id;
    }
}

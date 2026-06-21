<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Queue;

use NewInstance\BugWatch\Queue\EventQueue;
use PHPUnit\Framework\TestCase;

final class EventQueueTest extends TestCase
{
    public function test_drains_in_fifo_order(): void
    {
        $q = new EventQueue();
        $q->add(['eventId' => 'a']);
        $q->add(['eventId' => 'b']);

        self::assertSame(2, $q->count());
        self::assertSame([['eventId' => 'a']], $q->drained(1));
        self::assertSame([['eventId' => 'b']], $q->drained(10));
        self::assertTrue($q->isEmpty());
    }

    public function test_drops_oldest_on_overflow(): void
    {
        $q = new EventQueue(2);
        $q->add(['eventId' => 'a']);
        $q->add(['eventId' => 'b']);
        $q->add(['eventId' => 'c']);

        self::assertSame(2, $q->count());
        self::assertSame(1, $q->dropped());
        self::assertSame([['eventId' => 'b'], ['eventId' => 'c']], $q->drained(10));
    }
}

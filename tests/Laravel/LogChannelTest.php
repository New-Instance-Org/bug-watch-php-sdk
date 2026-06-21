<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Laravel;

use Illuminate\Support\Facades\Log;

final class LogChannelTest extends TestCase
{
    public function test_bugwatch_channel_sends_logs(): void
    {
        config()->set('logging.channels.bugwatch', ['driver' => 'bugwatch']);

        Log::channel('bugwatch')->error('checkout failed', ['order' => 42]);
        app(\NewInstance\BugWatch\Client::class)->flush();

        self::assertCount(1, $this->transport->events);
        self::assertSame(50, $this->transport->events[0]['level']);
        self::assertSame('checkout failed', $this->transport->events[0]['message']);
        self::assertSame('42', $this->transport->events[0]['tags']['order']);
    }

    public function test_exception_in_channel_context_is_captured(): void
    {
        config()->set('logging.channels.bugwatch', ['driver' => 'bugwatch']);

        Log::channel('bugwatch')->error('boom', ['exception' => new \RuntimeException('db down')]);
        app(\NewInstance\BugWatch\Client::class)->flush();

        self::assertSame(\RuntimeException::class, $this->transport->events[0]['exception']['type']);
    }
}

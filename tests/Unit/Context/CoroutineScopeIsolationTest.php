<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Context;

use NewInstance\BugWatch\Context\CoroutineContext;
use NewInstance\BugWatch\Context\CoroutineScopeStateResolver;
use NewInstance\BugWatch\Context\ScopeStack;
use NewInstance\BugWatch\Context\ScopeState;
use PHPUnit\Framework\TestCase;

/**
 * Proves the scope is coroutine-LOCAL when a {@see CoroutineContext} is supplied.
 *
 * The Swoole extension is not installed in this environment, so a deterministic
 * in-memory fake stands in for `\Swoole\Coroutine`: a settable current cid plus
 * a per-cid store of {@see ScopeState} objects.
 */
final class CoroutineScopeIsolationTest extends TestCase
{
    public function test_user_is_isolated_per_coroutine(): void
    {
        $fake = new FakeCoroutineContext();
        $stack = new ScopeStack(new CoroutineScopeStateResolver($fake));

        // Coroutine 1 sets user = alice.
        $fake->cid = 1;
        $stack->current()->user = ['id' => 'alice'];

        // Switch to coroutine 2: it must start with a clean, isolated scope.
        $fake->cid = 2;
        self::assertSame([], $stack->current()->user, 'coroutine 2 must not see coroutine 1 user');
        $stack->current()->user = ['id' => 'bob'];

        // Back to coroutine 1: still alice, NOT bob.
        $fake->cid = 1;
        self::assertSame(['id' => 'alice'], $stack->current()->user, 'coroutine 1 user must survive coroutine 2 writes');

        // And coroutine 2 still sees bob.
        $fake->cid = 2;
        self::assertSame(['id' => 'bob'], $stack->current()->user);
    }

    public function test_outside_coroutine_falls_back_to_shared_process_state(): void
    {
        $fake = new FakeCoroutineContext();
        $fake->cid = -1; // not inside a coroutine
        $resolver = new CoroutineScopeStateResolver($fake);
        $stack = new ScopeStack($resolver);

        $stack->current()->release = 'v1';
        // A second resolve outside any coroutine must hit the SAME shared state.
        self::assertSame('v1', $stack->current()->release);
        self::assertSame($resolver->resolve(), $resolver->resolve(), 'fallback state is a process-global singleton');
    }

    public function test_reset_is_per_coroutine(): void
    {
        $fake = new FakeCoroutineContext();
        $stack = new ScopeStack(new CoroutineScopeStateResolver($fake));

        $fake->cid = 1;
        $stack->current()->release = 'v1';
        $fake->cid = 2;
        $stack->current()->release = 'v2';

        // Reset coroutine 1 only.
        $fake->cid = 1;
        $stack->reset();
        self::assertNull($stack->current()->release);

        // Coroutine 2 is untouched.
        $fake->cid = 2;
        self::assertSame('v2', $stack->current()->release);
    }

    public function test_push_pop_is_per_coroutine(): void
    {
        $fake = new FakeCoroutineContext();
        $stack = new ScopeStack(new CoroutineScopeStateResolver($fake));

        $fake->cid = 1;
        $stack->current()->tags['env'] = 'prod';
        $child = $stack->push();
        $child->tags['op'] = 'checkout';

        // While coroutine 1 is mid-withScope, coroutine 2 sees only its own root.
        $fake->cid = 2;
        self::assertSame([], $stack->current()->tags);

        // Coroutine 1 still sees the pushed child layered on its root.
        $fake->cid = 1;
        self::assertSame(['env' => 'prod', 'op' => 'checkout'], $stack->current()->tags);

        $stack->pop();
        self::assertSame(['env' => 'prod'], $stack->current()->tags);
    }
}

/**
 * Deterministic stand-in for a Swoole coroutine runtime.
 *
 * @internal test double
 */
final class FakeCoroutineContext implements CoroutineContext
{
    public int $cid = -1;

    /** @var array<int,ScopeState> */
    private array $store = [];

    public function id(): int
    {
        return $this->cid;
    }

    public function get(): ?ScopeState
    {
        return $this->store[$this->cid] ?? null;
    }

    public function set(ScopeState $state): void
    {
        $this->store[$this->cid] = $state;
    }
}

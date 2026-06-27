<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

/**
 * Real {@see CoroutineContext} backed by the Swoole coroutine runtime
 * (also covers OpenSwoole / FrankenPHP-with-Swoole).
 *
 * Each coroutine gets its own context object from `\Swoole\Coroutine::getContext()`,
 * which Swoole automatically destroys when the coroutine ends — so a {@see ScopeState}
 * stored there is garbage-collected with the coroutine and never leaks across requests.
 *
 * This class must only ever be instantiated when the `swoole` extension is loaded
 * (see {@see self::__construct()} guard and the wiring in {@see \NewInstance\BugWatch\Client}).
 */
final class SwooleCoroutineContext implements CoroutineContext
{
    /** Key under which the per-coroutine ScopeState is stored in the coroutine context. */
    private const STATE_KEY = '__bugwatch_scope_state';

    public function __construct()
    {
        if (!\extension_loaded('swoole')) {
            throw new \RuntimeException(
                'SwooleCoroutineContext requires the "swoole" extension to be loaded.',
            );
        }
    }

    public function id(): int
    {
        /** @phpstan-ignore-next-line class.notFound */
        return \Swoole\Coroutine::getCid();
    }

    public function get(): ?ScopeState
    {
        $ctx = $this->context();
        if ($ctx === null) {
            return null;
        }
        $state = $ctx[self::STATE_KEY] ?? null;

        return $state instanceof ScopeState ? $state : null;
    }

    public function set(ScopeState $state): void
    {
        $ctx = $this->context();
        if ($ctx === null) {
            return;
        }
        $ctx[self::STATE_KEY] = $state;
    }

    /**
     * The current coroutine's context store (an ArrayAccess object), or null
     * when called outside any coroutine.
     *
     * @return \ArrayAccess<string,mixed>|null
     */
    private function context(): ?\ArrayAccess
    {
        if (\Swoole\Coroutine::getCid() < 0) {
            return null;
        }

        /** @phpstan-ignore-next-line class.notFound */
        return \Swoole\Coroutine::getContext();
    }
}

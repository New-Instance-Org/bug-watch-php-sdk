<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

/**
 * Abstraction over a coroutine runtime's per-coroutine storage.
 *
 * Implementations expose the current coroutine id and read/write the
 * {@see ScopeState} bound to that coroutine. A negative {@see self::id()}
 * means "not inside a coroutine", in which case callers fall back to a
 * shared process-global state.
 */
interface CoroutineContext
{
    /** Current coroutine id, or a negative value when not in a coroutine. */
    public function id(): int;

    /** The state bound to the current coroutine, or null if none yet. */
    public function get(): ?ScopeState;

    /** Bind a state to the current coroutine. */
    public function set(ScopeState $state): void;
}

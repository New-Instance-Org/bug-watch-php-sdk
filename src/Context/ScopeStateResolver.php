<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

/**
 * Strategy that hands {@see ScopeStack} the {@see ScopeState} it should operate on.
 *
 * The default {@see ProcessScopeStateResolver} always returns one shared state
 * (process-global, identical to the historical behaviour). Under Swoole the
 * {@see CoroutineScopeStateResolver} returns a distinct state per coroutine so
 * concurrent coroutines cannot clobber each other's user/tags/context.
 */
interface ScopeStateResolver
{
    public function resolve(): ScopeState;
}

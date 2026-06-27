<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

/**
 * Mutable holder for a single scope lineage: the root scope plus the
 * push/pop stack of child scopes. One instance per isolation boundary
 * (process-global by default, or coroutine-local under Swoole).
 */
final class ScopeState
{
    /** @param list<Scope> $stack */
    public function __construct(
        public Scope $root = new Scope(),
        public array $stack = [],
    ) {
    }
}

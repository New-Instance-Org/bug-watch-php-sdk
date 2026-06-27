<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

/**
 * Default resolver: one process-global {@see ScopeState} shared by every caller.
 *
 * This reproduces the SDK's historical single-stack behaviour exactly and is used
 * on PHP-FPM, CLI, and any runtime where coroutines are not in play.
 */
final class ProcessScopeStateResolver implements ScopeStateResolver
{
    public function __construct(private ScopeState $state = new ScopeState())
    {
    }

    public function resolve(): ScopeState
    {
        return $this->state;
    }
}

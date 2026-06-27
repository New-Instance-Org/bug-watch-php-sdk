<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

/**
 * Resolver that hands each coroutine its own {@see ScopeState}.
 *
 * When the current {@see CoroutineContext::id()} is negative (not inside a
 * coroutine) a shared process-global fallback state is returned, so the
 * non-coroutine path behaves exactly like {@see ProcessScopeStateResolver}.
 */
final class CoroutineScopeStateResolver implements ScopeStateResolver
{
    private ScopeState $fallback;

    public function __construct(
        private CoroutineContext $context,
        ?ScopeState $fallback = null,
    ) {
        $this->fallback = $fallback ?? new ScopeState();
    }

    public function resolve(): ScopeState
    {
        if ($this->context->id() < 0) {
            // Not inside a coroutine: behave like ProcessScopeStateResolver.
            return $this->fallback;
        }

        $state = $this->context->get();
        if ($state === null) {
            $state = new ScopeState();
            $this->context->set($state);
        }

        return $state;
    }
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

final class ScopeStack
{
    public function __construct(
        private ScopeStateResolver $resolver = new ProcessScopeStateResolver(),
    ) {
    }

    public function current(): Scope
    {
        $state = $this->resolver->resolve();

        return $state->stack === [] ? $state->root : $state->stack[count($state->stack) - 1];
    }

    public function push(): Scope
    {
        $state = $this->resolver->resolve();
        $scope = $this->current()->clone();
        $state->stack[] = $scope;

        return $scope;
    }

    public function pop(): void
    {
        $state = $this->resolver->resolve();
        array_pop($state->stack);
    }

    public function reset(): void
    {
        $state = $this->resolver->resolve();
        $state->root = new Scope();
        $state->stack = [];
    }
}

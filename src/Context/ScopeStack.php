<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

final class ScopeStack
{
    private Scope $root;
    /** @var list<Scope> */
    private array $stack = [];

    public function __construct()
    {
        $this->root = new Scope();
    }

    public function current(): Scope
    {
        return $this->stack === [] ? $this->root : $this->stack[count($this->stack) - 1];
    }

    public function push(): Scope
    {
        $scope = $this->current()->clone();
        $this->stack[] = $scope;

        return $scope;
    }

    public function pop(): void
    {
        array_pop($this->stack);
    }

    public function reset(): void
    {
        $this->root = new Scope();
        $this->stack = [];
    }
}

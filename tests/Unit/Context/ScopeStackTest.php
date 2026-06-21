<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Context;

use NewInstance\BugWatch\Context\ScopeStack;
use PHPUnit\Framework\TestCase;

final class ScopeStackTest extends TestCase
{
    public function test_push_clones_and_pop_restores(): void
    {
        $stack = new ScopeStack();
        $stack->current()->tags['env'] = 'prod';

        $child = $stack->push();
        $child->tags['op'] = 'checkout';
        self::assertSame(['env' => 'prod', 'op' => 'checkout'], $stack->current()->tags);

        $stack->pop();
        self::assertSame(['env' => 'prod'], $stack->current()->tags);
    }

    public function test_reset_clears_root(): void
    {
        $stack = new ScopeStack();
        $stack->current()->release = 'v1';
        $stack->reset();
        self::assertNull($stack->current()->release);
    }
}

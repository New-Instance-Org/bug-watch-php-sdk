<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Context;

final class Scope
{
    /** @var array<string,string> */
    public array $user = [];
    /** @var array<string,string> */
    public array $tags = [];
    /** @var array<string,mixed> */
    public array $contexts = [];
    public ?string $release = null;
    /** @var string|array<int|string,mixed>|null */
    public string|array|null $fingerprint = null;

    public function clone(): self
    {
        $c = new self();
        $c->user = $this->user;
        $c->tags = $this->tags;
        $c->contexts = $this->contexts;
        $c->release = $this->release;
        $c->fingerprint = $this->fingerprint;

        return $c;
    }
}

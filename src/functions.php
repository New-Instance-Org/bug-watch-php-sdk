<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

/** @param array<string,mixed> $options */
function createClient(array $options): Client
{
    return new Client(Config::fromArray($options));
}

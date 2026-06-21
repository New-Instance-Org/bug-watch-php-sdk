<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

/** @param array<string,mixed> $options */
function createClient(array $options): Client
{
    return new Client(Config::fromArray($options));
}

/**
 * @param array{projectKey:string,endpoint?:string} $options
 * @return array{token:string,expiresAt:int}
 */
function mintBrowserSession(array $options): array
{
    return Session\BrowserSession::mint(
        $options['projectKey'],
        $options['endpoint'] ?? 'https://api.newinstance.cloud',
    );
}

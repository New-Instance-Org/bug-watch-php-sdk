<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

use NewInstance\BugWatch\Exception\ConfigException;
use NewInstance\BugWatch\Transport\RetryOptions;

final class Config
{
    /**
     * @param list<string> $sensitiveFields
     * @param (callable(array<string,mixed>):(array<string,mixed>|null))|null $beforeSend
     */
    private function __construct(
        public readonly string $projectKey,
        public readonly string $endpoint,
        public readonly ?string $sessionUrl,
        public readonly ?string $release,
        public readonly bool $enabled,
        public readonly bool $debug,
        public readonly float $sampleRate,
        public readonly array $sensitiveFields,
        public readonly int $maxQueueSize,
        public readonly int $batchSize,
        public readonly int $flushInterval,
        public readonly int $requestTimeout,
        public readonly RetryOptions $retry,
        public readonly ?object $httpClient,
        public readonly mixed $beforeSend,
    ) {
    }

    /** @param array<string,mixed> $o */
    public static function fromArray(array $o): self
    {
        $enabled = (bool) ($o['enabled'] ?? true);
        $projectKey = is_string($o['projectKey'] ?? null) ? $o['projectKey'] : '';
        $sessionUrl = is_string($o['sessionUrl'] ?? null) ? $o['sessionUrl'] : null;

        if ($enabled && $projectKey === '' && $sessionUrl === null) {
            throw new ConfigException('BugWatch: "projectKey" (or "sessionUrl") is required.');
        }

        $endpoint = is_string($o['endpoint'] ?? null) ? $o['endpoint'] : 'https://api.newinstance.cloud';
        self::assertUrl($endpoint, 'endpoint');
        if ($sessionUrl !== null) {
            self::assertUrl($sessionUrl, 'sessionUrl');
        }

        $sampleRate = self::toFloat($o['sampleRate'] ?? null, 1.0);
        if ($sampleRate < 0.0 || $sampleRate > 1.0) {
            throw new ConfigException('BugWatch: "sampleRate" must be between 0 and 1.');
        }

        $batchSize = self::toInt($o['batchSize'] ?? null, 50);
        if ($batchSize < 1 || $batchSize > 5000) {
            throw new ConfigException('BugWatch: "batchSize" must be between 1 and 5000.');
        }

        $beforeSend = $o['beforeSend'] ?? null;
        if ($beforeSend !== null && !is_callable($beforeSend)) {
            throw new ConfigException('BugWatch: "beforeSend" must be callable.');
        }

        $retry = ($o['retry'] ?? null) instanceof RetryOptions ? $o['retry'] : new RetryOptions();
        $httpClient = is_object($o['httpClient'] ?? null) ? $o['httpClient'] : null;

        return new self(
            projectKey: $projectKey,
            endpoint: rtrim($endpoint, '/'),
            sessionUrl: $sessionUrl,
            release: is_string($o['release'] ?? null) ? $o['release'] : null,
            enabled: $enabled,
            debug: (bool) ($o['debug'] ?? false),
            sampleRate: $sampleRate,
            sensitiveFields: array_values(array_filter((array) ($o['sensitiveFields'] ?? []), 'is_string')),
            maxQueueSize: max(1, self::toInt($o['maxQueueSize'] ?? null, 1000)),
            batchSize: $batchSize,
            flushInterval: max(0, self::toInt($o['flushInterval'] ?? null, 0)),
            requestTimeout: max(1, self::toInt($o['requestTimeout'] ?? null, 15000)),
            retry: $retry,
            httpClient: $httpClient,
            beforeSend: $beforeSend,
        );
    }

    private static function toFloat(mixed $v, float $default): float
    {
        return is_numeric($v) ? (float) $v : $default;
    }

    private static function toInt(mixed $v, int $default): int
    {
        return is_numeric($v) ? (int) $v : $default;
    }

    private static function assertUrl(string $url, string $field): void
    {
        if (!preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ConfigException(sprintf('BugWatch: "%s" must be a valid http(s) URL.', $field));
        }
    }
}

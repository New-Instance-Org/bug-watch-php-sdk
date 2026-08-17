<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tracing;

use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Sdk;

final class SpanExporter
{
    private const PATH = '/v1/traces';
    private const MAX_BUFFER = 200;
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /** @var list<array<string,mixed>> */
    private array $buffer = [];

    /** @var callable(string,list<string>,string):array{status:int} */
    private $sender;

    public function __construct(private readonly Config $config, ?callable $sender = null)
    {
        $this->sender = $sender ?? $this->makeDefaultSender();
    }

    /** @param array<string,mixed> $span */
    public function add(array $span): void
    {
        if (!$this->config->enabled || $this->config->projectKey === '') {
            return;
        }
        $this->buffer[] = $span;
        if (count($this->buffer) > self::MAX_BUFFER) {
            array_shift($this->buffer);
        }
        if (count($this->buffer) >= $this->config->batchSize) {
            $this->flush();
        }
    }

    public function flush(): bool
    {
        if ($this->buffer === []) {
            return true;
        }
        $batch = $this->buffer;
        $this->buffer = [];
        try {
            $body = (string) json_encode(self::toOtlpJson($batch, $this->config->serviceName), self::JSON_FLAGS);
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->config->projectKey];
            $res = ($this->sender)($body, $headers, $this->config->endpoint . self::PATH);
            $status = (int) ($res['status'] ?? 0);

            return $status >= 200 && $status < 300;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<array<string,mixed>> $spans
     * @return array<string,mixed>
     */
    public static function toOtlpJson(array $spans, ?string $serviceName): array
    {
        $resourceAttrs = self::attrList(array_filter([
            'service.name' => $serviceName,
            'telemetry.sdk.name' => Sdk::NAME,
            'telemetry.sdk.version' => Sdk::VERSION,
        ], static fn ($v) => $v !== null));

        return [
            'resourceSpans' => [[
                'resource' => ['attributes' => $resourceAttrs],
                'scopeSpans' => [[
                    'scope' => ['name' => Sdk::NAME, 'version' => Sdk::VERSION],
                    'spans' => array_map(self::mapSpan(...), $spans),
                ]],
            ]],
        ];
    }

    private static function nano(mixed $ms): string
    {
        return sprintf('%.0f', (is_numeric($ms) ? (float) $ms : 0.0) * 1_000_000);
    }

    /**
     * @param array<string,mixed> $s
     * @return array<string,mixed>
     */
    private static function mapSpan(array $s): array
    {
        $events = [];
        foreach (is_array($s['events'] ?? null) ? $s['events'] : [] as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $evAttrs = is_array($ev['attrs'] ?? null) ? $ev['attrs'] : [];
            $events[] = [
                'name' => $ev['name'] ?? '',
                'timeUnixNano' => self::nano($ev['timeMs'] ?? 0),
                'attributes' => self::attrList($evAttrs),
            ];
        }
        $out = [
            'traceId' => $s['traceId'] ?? '',
            'spanId' => $s['spanId'] ?? '',
            'name' => $s['name'] ?? '',
            'kind' => $s['kind'] ?? 1,
            'startTimeUnixNano' => self::nano($s['startMs'] ?? 0),
            'endTimeUnixNano' => self::nano($s['endMs'] ?? 0),
            'status' => array_filter([
                'code' => $s['statusCode'] ?? 0,
                'message' => $s['statusMessage'] ?? null,
            ], static fn ($v) => $v !== null),
            'attributes' => self::attrList(is_array($s['attrs'] ?? null) ? $s['attrs'] : []),
            'events' => $events,
        ];
        $links = [];
        foreach (is_array($s['links'] ?? null) ? $s['links'] : [] as $link) {
            if (!is_array($link)) {
                continue;
            }
            $links[] = [
                'traceId' => $link['traceId'] ?? '',
                'spanId' => $link['spanId'] ?? '',
                'attributes' => self::attrList(is_array($link['attrs'] ?? null) ? $link['attrs'] : []),
            ];
        }
        if ($links !== []) {
            $out['links'] = $links;
        }
        if (isset($s['parentSpanId'])) {
            $out['parentSpanId'] = $s['parentSpanId'];
        }

        return $out;
    }

    /**
     * @param array<array-key,mixed> $attrs
     * @return list<array{key:string,value:array{stringValue:string}}>
     */
    private static function attrList(array $attrs): array
    {
        $out = [];
        foreach ($attrs as $k => $v) {
            if (is_scalar($v)) {
                $out[] = ['key' => (string) $k, 'value' => ['stringValue' => (string) $v]];
            }
        }

        return $out;
    }

    /** @return callable(string,list<string>,string):array{status:int} */
    private function makeDefaultSender(): callable
    {
        $timeout = $this->config->requestTimeout;

        return static function (string $body, array $headers, string $url) use ($timeout): array {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['status' => 0];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => $timeout,
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

            return ['status' => $status];
        };
    }
}

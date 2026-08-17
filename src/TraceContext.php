<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

final class TraceContext
{
    public static function normalizeTraceId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $lower = strtolower($value);
        if (preg_match('/^[0-9a-f]{32}$/', $lower) !== 1) {
            return null;
        }
        if ($lower === str_repeat('0', 32)) {
            return null;
        }

        return $lower;
    }

    public static function normalizeSpanId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $lower = strtolower($value);
        if (preg_match('/^[0-9a-f]{16}$/', $lower) !== 1) {
            return null;
        }
        if ($lower === str_repeat('0', 16)) {
            return null;
        }

        return $lower;
    }

    /** @return array{traceId:string,spanId:string}|null */
    public static function parseTraceparent(mixed $header): ?array
    {
        if (!is_string($header)) {
            return null;
        }
        $parts = explode('-', trim($header));
        if (count($parts) < 4) {
            return null;
        }
        $traceId = self::normalizeTraceId($parts[1]);
        $spanId = self::normalizeSpanId($parts[2]);
        if ($traceId === null || $spanId === null) {
            return null;
        }

        return ['traceId' => $traceId, 'spanId' => $spanId];
    }

    public static function buildTraceparent(mixed $traceId, mixed $spanId): ?string
    {
        $t = self::normalizeTraceId($traceId);
        $s = self::normalizeSpanId($spanId);
        if ($t === null || $s === null) {
            return null;
        }

        return sprintf('00-%s-%s-01', $t, $s);
    }
}

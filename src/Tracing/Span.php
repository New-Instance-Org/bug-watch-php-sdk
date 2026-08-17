<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tracing;

final class Span
{
    private const MAX_ATTRS = 50;
    private const MAX_ATTR_VALUE = 200;
    private const MAX_STACK_VALUE = 8000;
    private const MAX_EVENTS = 20;

    public readonly string $traceId;
    public readonly string $spanId;
    public readonly ?string $parentSpanId;
    public readonly string $name;
    public readonly int $kind;
    public readonly float $startMs;

    /** @var array<string,string> */
    private array $attrs = [];
    /** @var list<array{name:string,timeMs:float,attrs:array<string,string>}> */
    private array $events = [];
    /** @var list<array{traceId:string,spanId:string,attrs:array<string,string>}> */
    private array $links = [];
    private int $statusCode = 0;
    private ?string $statusMessage = null;
    private bool $ended = false;

    /** @var callable(array<string,mixed>):void */
    private $sink;

    /**
     * @param callable(array<string,mixed>):void $sink
     * @param array{kind?:int,attrs?:array<string,mixed>,traceId?:?string,parentSpanId?:?string,links?:list<array{traceId:string,spanId:string,attrs?:array<string,mixed>}>} $options
     */
    public function __construct(callable $sink, string $name, ?string $parentTraceId, ?string $parentSpanId, array $options = [])
    {
        $this->sink = $sink;
        $this->name = substr($name, 0, 200) !== '' ? substr($name, 0, 200) : 'span';
        $this->kind = is_int($options['kind'] ?? null) ? $options['kind'] : 1;
        $this->startMs = microtime(true) * 1000;
        $traceId = is_string($options['traceId'] ?? null) ? $options['traceId'] : $parentTraceId;
        $this->traceId = $traceId ?? TraceIds::generateTraceId();
        $parent = is_string($options['parentSpanId'] ?? null) ? $options['parentSpanId'] : $parentSpanId;
        $this->parentSpanId = $parent;
        $this->spanId = TraceIds::generateSpanId();
        $this->setAttrs($options['attrs'] ?? []);
        foreach (is_array($options['links'] ?? null) ? array_slice($options['links'], 0, 10) : [] as $link) {
            if (!is_array($link)) {
                continue;
            }
            $traceId = \NewInstance\BugWatch\TraceContext::normalizeTraceId($link['traceId'] ?? null);
            $spanId = \NewInstance\BugWatch\TraceContext::normalizeSpanId($link['spanId'] ?? null);
            if ($traceId === null || $spanId === null) {
                continue;
            }
            $attrs = [];
            foreach (is_array($link['attrs'] ?? null) ? $link['attrs'] : [] as $k => $v) {
                if (is_scalar($v) && count($attrs) < self::MAX_ATTRS) {
                    $attrs[substr((string) $k, 0, self::MAX_ATTR_VALUE)] = substr((string) $v, 0, self::MAX_ATTR_VALUE);
                }
            }
            $this->links[] = ['traceId' => $traceId, 'spanId' => $spanId, 'attrs' => $attrs];
        }
    }

    /** @param array<string,mixed> $attrs */
    public function setAttrs(mixed $attrs): self
    {
        if (!is_array($attrs)) {
            return $this;
        }
        foreach ($attrs as $k => $v) {
            if (count($this->attrs) >= self::MAX_ATTRS) {
                break;
            }
            if (is_scalar($v)) {
                $key = substr((string) $k, 0, self::MAX_ATTR_VALUE);
                $this->attrs[$key] = substr(is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, 0, self::MAX_ATTR_VALUE);
            }
        }

        return $this;
    }

    public function setAttr(string $key, mixed $value): self
    {
        return $this->setAttrs([$key => $value]);
    }

    public function recordException(\Throwable $e): self
    {
        if (count($this->events) < self::MAX_EVENTS) {
            $this->events[] = [
                'name' => 'exception',
                'timeMs' => microtime(true) * 1000,
                'attrs' => [
                    'exception.type' => $e::class,
                    'exception.message' => substr($e->getMessage(), 0, self::MAX_STACK_VALUE),
                    'exception.stacktrace' => substr($e->getTraceAsString(), 0, self::MAX_STACK_VALUE),
                ],
            ];
        }
        $this->statusCode = 2;

        return $this;
    }

    public function end(?int $statusCode = null, ?string $statusMessage = null): void
    {
        if ($this->ended) {
            return;
        }
        $this->ended = true;
        if ($statusCode !== null) {
            $this->statusCode = $statusCode;
        }
        if ($statusMessage !== null) {
            $this->statusMessage = substr($statusMessage, 0, 200);
        }
        $data = [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'name' => $this->name,
            'kind' => $this->kind,
            'startMs' => $this->startMs,
            'endMs' => microtime(true) * 1000,
            'statusCode' => $this->statusCode,
            'attrs' => $this->attrs,
            'events' => $this->events,
            'links' => $this->links,
        ];
        if ($this->parentSpanId !== null) {
            $data['parentSpanId'] = $this->parentSpanId;
        }
        if ($this->statusMessage !== null) {
            $data['statusMessage'] = $this->statusMessage;
        }
        try {
            ($this->sink)($data);
        } catch (\Throwable) {
        }
    }

    public function traceparent(): string
    {
        return sprintf('00-%s-%s-01', $this->traceId, $this->spanId);
    }
}

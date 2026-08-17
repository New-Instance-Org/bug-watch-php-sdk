<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Tracing;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Testing\InMemoryTransport;
use NewInstance\BugWatch\Tracing\Span;
use NewInstance\BugWatch\Tracing\SpanExporter;
use NewInstance\BugWatch\Tracing\TraceIds;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    /** @var list<array<string,mixed>> */
    private array $captured = [];

    private function sink(): callable
    {
        return function (array $s): void {
            $this->captured[] = $s;
        };
    }

    public function testIdsAreValidHex(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', TraceIds::generateTraceId());
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', TraceIds::generateSpanId());
    }

    public function testSpanEndsOnceWithAttrsAndInheritedTrace(): void
    {
        $trace = str_repeat('a', 32);
        $span = new Span($this->sink(), 'db.query', $trace, str_repeat('b', 16), [
            'kind' => 3,
            'attrs' => ['db.system' => 'mysql', 'n' => 7],
        ]);
        $span->setAttr('db.table', 'orders');
        $span->end();
        $span->end();
        self::assertCount(1, $this->captured);
        $d = $this->captured[0];
        self::assertSame($trace, $d['traceId']);
        self::assertSame(str_repeat('b', 16), $d['parentSpanId']);
        self::assertSame(3, $d['kind']);
        self::assertSame(['db.system' => 'mysql', 'n' => '7', 'db.table' => 'orders'], $d['attrs']);
        self::assertGreaterThanOrEqual($d['startMs'], $d['endMs']);
    }

    public function testRecordExceptionSetsErrorStatusAndEvent(): void
    {
        $span = new Span($this->sink(), 'op', null, null);
        $span->recordException(new \RuntimeException('boom'));
        $span->end();
        $d = $this->captured[0];
        self::assertSame(2, $d['statusCode']);
        self::assertSame('exception', $d['events'][0]['name']);
        self::assertSame(\RuntimeException::class, $d['events'][0]['attrs']['exception.type']);
        self::assertStringContainsString('SpanTest', $d['events'][0]['attrs']['exception.stacktrace']);
    }

    public function testWithSpanLinksCapturesAndRestoresScope(): void
    {
        $transport = new InMemoryTransport();
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), $transport);
        $ref = new \ReflectionProperty($client, 'spanExporter');
        $ref->setValue($client, new SpanExporter(
            Config::fromArray(['projectKey' => 'k:s']),
            static fn (): array => ['status' => 200],
        ));
        $inside = null;
        $result = $client->withSpan('job.run', function (Span $span) use ($client, &$inside): int {
            $inside = $client->getTraceContext();
            $client->captureMessage('inside span');

            return 42;
        });
        self::assertSame(42, $result);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $inside['traceId']);
        self::assertNull($client->getTraceContext()['traceId']);
        $client->flush();
        self::assertSame($inside['traceId'], $transport->events[0]['traceId']);
        self::assertSame($inside['spanId'], $transport->events[0]['spanId']);
    }

    public function testWithSpanRecordsAndRethrows(): void
    {
        $client = new Client(Config::fromArray(['projectKey' => 'k:s']), new InMemoryTransport());
        $ref = new \ReflectionProperty($client, 'spanExporter');
        $ref->setValue($client, new SpanExporter(
            Config::fromArray(['projectKey' => 'k:s']),
            static fn (): array => ['status' => 200],
        ));
        try {
            $client->withSpan('explode', static function (): void {
                throw new \LogicException('kaboom');
            });
            self::fail('expected throw');
        } catch (\LogicException $e) {
            self::assertSame('kaboom', $e->getMessage());
        }
    }

    public function testLinksAreValidatedAndExported(): void
    {
        $producerTrace = str_repeat('c', 32);
        $producerSpan = str_repeat('d', 16);
        $span = new Span($this->sink(), 'emails process', null, null, [
            'kind' => 5,
            'links' => [
                ['traceId' => $producerTrace, 'spanId' => $producerSpan, 'attrs' => ['messaging.message.id' => 'm-9']],
                ['traceId' => 'bad', 'spanId' => 'worse'],
            ],
        ]);
        $span->end();
        $d = $this->captured[0];
        self::assertCount(1, $d['links']);
        self::assertSame($producerTrace, $d['links'][0]['traceId']);
        $otlp = SpanExporter::toOtlpJson([$d], 'email-worker');
        $out = $otlp['resourceSpans'][0]['scopeSpans'][0]['spans'][0];
        self::assertSame($producerSpan, $out['links'][0]['spanId']);
        self::assertSame('messaging.message.id', $out['links'][0]['attributes'][0]['key']);
    }

    public function testExporterPostsOtlpJson(): void
    {
        $posts = [];
        $exporter = new SpanExporter(
            Config::fromArray(['projectKey' => 'k:s', 'serviceName' => 'refund-worker', 'endpoint' => 'http://localhost:5050']),
            function (string $body, array $headers, string $url) use (&$posts): array {
                $posts[] = ['body' => $body, 'headers' => $headers, 'url' => $url];

                return ['status' => 200];
            },
        );
        $span = new Span(static fn (array $s) => $exporter->add($s), 'refund.process', null, null, ['kind' => 5]);
        $span->end();
        self::assertTrue($exporter->flush());
        self::assertCount(1, $posts);
        self::assertSame('http://localhost:5050/v1/traces', $posts[0]['url']);
        self::assertContains('x-api-key: k:s', $posts[0]['headers']);
        $decoded = json_decode($posts[0]['body'], true);
        $spanOut = $decoded['resourceSpans'][0]['scopeSpans'][0]['spans'][0];
        self::assertSame(5, $spanOut['kind']);
        self::assertMatchesRegularExpression('/^\d+$/', $spanOut['startTimeUnixNano']);
        $resourceKeys = array_column($decoded['resourceSpans'][0]['resource']['attributes'], 'key');
        self::assertContains('service.name', $resourceKeys);
    }
}

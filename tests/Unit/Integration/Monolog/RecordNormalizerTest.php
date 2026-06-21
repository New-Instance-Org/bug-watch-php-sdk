<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Integration\Monolog;

use Monolog\Level;
use Monolog\LogRecord;
use NewInstance\BugWatch\Integration\Monolog\RecordNormalizer;
use PHPUnit\Framework\TestCase;

final class RecordNormalizerTest extends TestCase
{
    public function test_monolog3_logrecord(): void
    {
        $rec = new LogRecord(new \DateTimeImmutable(), 'payments', Level::Error, 'charge failed {id}', ['id' => 7, 'svc' => 'api'], ['pid' => 99]);
        $n = RecordNormalizer::normalize($rec);

        self::assertSame(400, $n['level']);            // Monolog ERROR int
        self::assertSame('charge failed {id}', $n['message']);
        self::assertSame('payments', $n['channel']);
        self::assertNull($n['exception']);
        self::assertSame('payments', $n['tags']['channel']);
        self::assertSame(7, $n['tags']['id']);
        self::assertSame('api', $n['tags']['svc']);
        self::assertSame(99, $n['tags']['pid']);
    }

    public function test_monolog3_extracts_exception_from_context(): void
    {
        $e = new \RuntimeException('boom');
        $rec = new LogRecord(new \DateTimeImmutable(), 'app', Level::Critical, 'failed', ['exception' => $e], []);
        $n = RecordNormalizer::normalize($rec);

        self::assertSame($e, $n['exception']);
        self::assertArrayNotHasKey('exception', $n['tags']);
        self::assertSame(500, $n['level']);
    }

    public function test_monolog2_array_record_shape(): void
    {
        // Monolog 2 passes an array to write(); normalize must handle it without Monolog 2 installed.
        $record = [
            'message' => 'disk full',
            'level' => 300,                 // WARNING
            'channel' => 'sys',
            'context' => ['mount' => '/data'],
            'extra' => ['host' => 'web1'],
        ];
        $n = RecordNormalizer::normalize($record);

        self::assertSame(300, $n['level']);
        self::assertSame('disk full', $n['message']);
        self::assertSame('sys', $n['channel']);
        self::assertSame('/data', $n['tags']['mount']);
        self::assertSame('web1', $n['tags']['host']);
    }

    public function test_junk_record_defaults(): void
    {
        $n = RecordNormalizer::normalize('not a record');
        self::assertSame(200, $n['level']);
        self::assertSame('', $n['message']);
        self::assertNull($n['exception']);
    }
}

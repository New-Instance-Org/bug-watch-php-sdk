<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Serializer;

use NewInstance\BugWatch\Serializer\ExceptionSerializer;
use PHPUnit\Framework\TestCase;

final class ExceptionSerializerTest extends TestCase
{
    public function test_serialize_basic_throwable(): void
    {
        $e = new \RuntimeException('boom');
        $out = ExceptionSerializer::serialize($e);

        self::assertSame(\RuntimeException::class, $out['type']);
        self::assertSame('boom', $out['value']);
        self::assertNotEmpty($out['stacktrace']);
        self::assertSame(__FILE__, $out['stacktrace'][0]['filename']);
        self::assertTrue($out['stacktrace'][0]['in_app']);
    }

    public function test_value_is_clamped_to_2048(): void
    {
        $out = ExceptionSerializer::serialize(new \RuntimeException(str_repeat('x', 5000)));
        self::assertSame(2048, mb_strlen($out['value']));
    }

    public function test_causes_walks_chain_max_three(): void
    {
        $root = new \RuntimeException('root');
        $mid1 = new \RuntimeException('m1', 0, $root);
        $mid2 = new \RuntimeException('m2', 0, $mid1);
        $mid3 = new \RuntimeException('m3', 0, $mid2);
        $top = new \RuntimeException('top', 0, $mid3);

        $causes = ExceptionSerializer::causes($top);

        self::assertCount(3, $causes);
        self::assertSame('m3', $causes[0]['value']);
        self::assertSame('m1', $causes[2]['value']);
    }

    public function test_frame_function_recombines_class_and_method(): void
    {
        $e = (new class () {
            public function trigger(): \Throwable
            {
                return new \LogicException('x');
            }
        })->trigger();

        $out = ExceptionSerializer::serialize($e);
        $functions = array_column($out['stacktrace'], 'function');
        self::assertNotEmpty(array_filter($functions, static fn ($f) => str_contains($f, '::trigger')));
    }
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Serializer;

final class ExceptionSerializer
{
    private const MAX_FRAMES = 50;
    private const MAX_VALUE = 2048;
    private const MAX_CHAIN = 3;

    /** @return array{type:string,value:string,stacktrace:list<array{filename:string,function:string,lineno:int,in_app:bool}>} */
    public static function serialize(\Throwable $e): array
    {
        return [
            'type' => $e::class,
            'value' => self::clamp($e->getMessage()),
            'stacktrace' => self::frames($e),
        ];
    }

    /** @return list<array{type:string,value:string}> */
    public static function causes(\Throwable $e): array
    {
        $out = [];
        $prev = $e->getPrevious();
        $depth = 0;
        while ($prev !== null && $depth < self::MAX_CHAIN) {
            $out[] = ['type' => $prev::class, 'value' => self::clamp($prev->getMessage())];
            $prev = $prev->getPrevious();
            $depth++;
        }

        return $out;
    }

    /** @return list<array{filename:string,function:string,lineno:int,in_app:bool}> */
    private static function frames(\Throwable $e): array
    {
        $frames = [[
            'filename' => $e->getFile(),
            'function' => '',
            'lineno' => $e->getLine(),
            'in_app' => self::inApp($e->getFile()),
        ]];

        foreach ($e->getTrace() as $t) {
            if (count($frames) >= self::MAX_FRAMES) {
                break;
            }
            $file = is_string($t['file'] ?? null) ? $t['file'] : '[internal]';
            $class = $t['class'] ?? '';
            $function = $t['function'] ?? '';
            $fn = $class !== '' ? $class . '::' . $function : $function;
            $frames[] = [
                'filename' => $file,
                'function' => $fn,
                'lineno' => is_int($t['line'] ?? null) ? $t['line'] : 0,
                'in_app' => self::inApp($file),
            ];
        }

        return $frames;
    }

    private static function inApp(string $file): bool
    {
        return $file !== '' && $file !== '[internal]' && !str_contains($file, '/vendor/');
    }

    private static function clamp(string $s): string
    {
        return mb_substr($s, 0, self::MAX_VALUE);
    }
}

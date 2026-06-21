<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Integration\Monolog;

final class RecordNormalizer
{
    /**
     * @return array{level:int|string, message:string, channel:string, exception:?\Throwable, tags:array<string,scalar>}
     */
    public static function normalize(mixed $record): array
    {
        $level = 200;
        $message = '';
        $channel = '';
        $context = [];
        $extra = [];

        if (is_object($record) && $record instanceof \Monolog\LogRecord) {
            $level = $record->level->value; // Monolog\Level enum -> int (100-600)
            $message = $record->message;
            $channel = $record->channel;
            $context = $record->context;
            $extra = $record->extra;
        } elseif (is_array($record)) {
            $rawLevel = $record['level'] ?? 200;
            $level = is_int($rawLevel) || is_string($rawLevel) ? $rawLevel : 200;
            $message = is_scalar($record['message'] ?? null) ? (string) $record['message'] : '';
            $channel = is_scalar($record['channel'] ?? null) ? (string) $record['channel'] : '';
            $context = is_array($record['context'] ?? null) ? $record['context'] : [];
            $extra = is_array($record['extra'] ?? null) ? $record['extra'] : [];
        }

        $exception = null;
        if (($context['exception'] ?? null) instanceof \Throwable) {
            $exception = $context['exception'];
            unset($context['exception']);
        }

        /** @var array<string,scalar> $tags */
        $tags = [];
        if ($channel !== '') {
            $tags['channel'] = $channel;
        }
        foreach ([...$context, ...$extra] as $k => $v) {
            if (is_scalar($v)) {
                $tags[(string) $k] = $v;
            }
        }

        return ['level' => $level, 'message' => $message, 'channel' => $channel, 'exception' => $exception, 'tags' => $tags];
    }
}

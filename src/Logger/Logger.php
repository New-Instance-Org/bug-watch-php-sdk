<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Logger;

use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Severity;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class Logger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(private readonly Client $client)
    {
    }

    /** @param array<string,mixed> $context */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $text = self::interpolate((string) $message, $context);
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        if ($exception instanceof \Throwable) {
            $this->client->captureException($exception, [
                'level' => is_string($level) ? Severity::toNumber($level) : $level,
                'tags' => $context,
            ]);

            return;
        }

        $this->client->captureLog([
            'level' => $level,
            'message' => $text,
            'tags' => $context,
        ]);
    }

    /** @param array<string,mixed> $context */
    private static function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }
}

<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Integration\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use NewInstance\BugWatch\Client;

final class Handler extends AbstractProcessingHandler
{
    /**
     * @phpstan-param value-of<\Monolog\Level::VALUES>|value-of<\Monolog\Level::NAMES>|\Monolog\Level|\Psr\Log\LogLevel::* $level
     */
    public function __construct(private readonly Client $client, int|string|\Monolog\Level $level = 'debug', bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * Monolog 2 passes an array; Monolog 3 passes a \Monolog\LogRecord. The parameter type is
     * intentionally omitted (contravariant widening, valid PHP 7.4+) so this satisfies BOTH parent
     * signatures (`write(array)` in v2, `write(LogRecord)` in v3).
     *
     * @param \Monolog\LogRecord|array<string,mixed> $record
     */
    protected function write($record): void
    {
        try {
            $n = RecordNormalizer::normalize($record);
            if ($n['exception'] !== null) {
                $this->client->captureException($n['exception'], ['level' => $n['level'], 'tags' => $n['tags']]);

                return;
            }
            $this->client->captureLog(['level' => $n['level'], 'message' => $n['message'], 'tags' => $n['tags']]);
        } catch (\Throwable) {
            // never throw into Monolog
        }
    }
}

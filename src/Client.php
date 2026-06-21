<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

use NewInstance\BugWatch\Context\ScopeStack;
use NewInstance\BugWatch\Logger\Logger;
use NewInstance\BugWatch\Queue\EventQueue;
use NewInstance\BugWatch\Redaction\Redactor;
use NewInstance\BugWatch\Transport\HttpTransport;
use NewInstance\BugWatch\Transport\NullTransport;
use NewInstance\BugWatch\Transport\RetryPolicy;
use NewInstance\BugWatch\Transport\TransportInterface;

final class Client
{
    private ScopeStack $scopes;
    private EventQueue $queue;
    private Normalizer $normalizer;
    private TransportInterface $transport;
    private Diagnostics $diagnostics;
    /** @var \WeakMap<\Throwable,string> */
    private \WeakMap $captured;
    private ?Logger $logger = null;

    public function __construct(
        public readonly Config $config,
        ?TransportInterface $transport = null,
        ?Diagnostics $diagnostics = null,
    ) {
        $this->diagnostics = $diagnostics ?? new Diagnostics($config->debug);
        $this->scopes = new ScopeStack();
        $this->queue = new EventQueue($config->maxQueueSize);
        $this->normalizer = new Normalizer($config, new Redactor($config->sensitiveFields));
        $this->transport = $transport ?? ($config->enabled
            ? new HttpTransport($config, new RetryPolicy($config->retry))
            : new NullTransport());
        $this->captured = new \WeakMap();
    }

    /** @param array<string,mixed> $hint */
    public function captureException(\Throwable $e, array $hint = []): string
    {
        return $this->guard(function () use ($e, $hint): string {
            if (isset($this->captured[$e])) {
                return $this->captured[$e];
            }
            $id = $this->capture([
                'exception' => $e,
                'level' => $hint['level'] ?? 'error',
                'tags' => $hint['tags'] ?? [],
                'user' => $hint['user'] ?? [],
            ]);
            $this->captured[$e] = $id;

            return $id;
        });
    }

    public function captureMessage(string $message, int|string $level = 'info'): string
    {
        return $this->guard(fn (): string => $this->capture(['message' => $message, 'level' => $level]));
    }

    /** @param array<string,mixed> $input */
    public function captureLog(array $input): string
    {
        return $this->guard(fn (): string => $this->capture($input));
    }

    /** @param array<string,mixed> $input */
    private function capture(array $input): string
    {
        if (!$this->config->enabled) {
            return '';
        }
        if ($this->config->sampleRate < 1.0 && (mt_rand() / mt_getrandmax()) >= $this->config->sampleRate) {
            return is_string($input['eventId'] ?? null) ? $input['eventId'] : EventId::generate();
        }

        $event = $this->normalizer->build($input, $this->scopes->current());
        if ($event === []) {
            return '';
        }

        $this->queue->add($event);
        if ($this->queue->count() >= $this->config->batchSize) {
            $this->flush();
        }

        return is_string($event['eventId']) ? $event['eventId'] : '';
    }

    public function flush(): bool
    {
        return (bool) $this->guard(function (): bool {
            $ok = true;
            while (!$this->queue->isEmpty()) {
                $batch = $this->queue->drained($this->config->batchSize);
                if (!$this->transport->send($batch)) {
                    $ok = false;
                    $this->diagnostics->incr('dropped_batches');
                }
            }

            return $ok;
        }, false);
    }

    public function close(): void
    {
        $this->flush();
    }

    /** @param array<string,mixed>|null $user */
    public function setUser(?array $user): void
    {
        $this->scopes->current()->user = $user === null ? [] : Normalizer::userPick($user);
    }

    public function setTag(string $key, mixed $value): void
    {
        if (is_scalar($value)) {
            $this->scopes->current()->tags[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
    }

    /** @param array<string,mixed> $kv */
    public function setTags(array $kv): void
    {
        $this->scopes->current()->tags = array_merge($this->scopes->current()->tags, Normalizer::stringMap($kv));
    }

    public function setContext(string $key, mixed $data): void
    {
        $this->scopes->current()->contexts[$key] = $data;
    }

    public function setRelease(string $release): void
    {
        $this->scopes->current()->release = $release;
    }

    /** @param string|array<int|string,mixed> $fingerprint */
    public function setFingerprint(string|array $fingerprint): void
    {
        $this->scopes->current()->fingerprint = $fingerprint;
    }

    public function withScope(callable $callback): void
    {
        $scope = $this->scopes->push();
        try {
            $callback($scope);
        } catch (\Throwable $e) {
            $this->diagnostics->error('withScope callback threw', $e);
        } finally {
            $this->scopes->pop();
        }
    }

    public function resetScope(): void
    {
        $this->scopes->reset();
    }

    public function getLogger(): Logger
    {
        return $this->logger ??= new Logger($this);
    }

    public function diagnostics(): Diagnostics
    {
        return $this->diagnostics;
    }

    /**
     * @template T
     * @param callable():T $fn
     * @param T|string $fallback
     * @return T|string
     */
    private function guard(callable $fn, mixed $fallback = ''): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $this->diagnostics->error('capture failure', $e);

            return $fallback;
        }
    }
}

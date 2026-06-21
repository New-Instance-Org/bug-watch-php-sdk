<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Handlers;

use NewInstance\BugWatch\Client;

final class ErrorHandler
{
    private static ?self $instance = null;
    private bool $inHandler = false;
    /** @var callable|null */
    private $previousExceptionHandler = null;
    private bool $exceptionRegistered = false;
    private bool $errorRegistered = false;
    private bool $shutdownRegistered = false;

    private function __construct(private Client $client)
    {
    }

    /** @param array{errors?:bool,exceptions?:bool,shutdown?:bool} $opts */
    public static function install(Client $client, array $opts = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($client);
        } else {
            self::$instance->client = $client;
        }
        $h = self::$instance;
        if (($opts['exceptions'] ?? true) && !$h->exceptionRegistered) {
            $h->exceptionRegistered = true;
            $h->previousExceptionHandler = set_exception_handler([$h, 'handleException']);
        }
        if (($opts['errors'] ?? true) && !$h->errorRegistered) {
            $h->errorRegistered = true;
            set_error_handler([$h, 'handleError']);
        }
        if (($opts['shutdown'] ?? true) && !$h->shutdownRegistered) {
            $h->shutdownRegistered = true;
            register_shutdown_function([$h, 'handleShutdown']);
        }

        return $h;
    }

    public function uninstall(): void
    {
        if ($this->exceptionRegistered) {
            restore_exception_handler();
            $this->exceptionRegistered = false;
        }
        if ($this->errorRegistered) {
            restore_error_handler();
            $this->errorRegistered = false;
        }
    }

    public function handleException(\Throwable $e): void
    {
        $this->safe(function () use ($e): void {
            $this->client->captureException($e, ['level' => 'fatal', 'tags' => ['handled' => 'false']]);
            $this->client->flush();
        });
        if ($this->previousExceptionHandler !== null) {
            ($this->previousExceptionHandler)($e);
        }
    }

    public function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        if (error_reporting() === 0) {
            return false; // suppressed via @ operator
        }
        $this->safe(function () use ($errno, $errstr, $errfile, $errline): void {
            $this->client->captureLog([
                'level' => self::errnoLevel($errno),
                'message' => $errstr,
                'tags' => ['file' => $errfile, 'line' => $errline, 'errno' => $errno, 'handled' => 'false'],
            ]);
        });

        return false; // chain: let PHP's normal handler run too
    }

    public function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $this->safe(function () use ($err): void {
            $this->client->captureLog([
                'level' => 'fatal',
                'message' => $err['message'],
                'tags' => ['file' => $err['file'], 'line' => $err['line'], 'errno' => $err['type'], 'fatal' => 'true'],
            ]);
            $this->client->flush();
        });
    }

    private function safe(callable $fn): void
    {
        if ($this->inHandler) {
            return;
        }
        $this->inHandler = true;
        try {
            $fn();
        } catch (\Throwable) {
            // never let the SDK's own handler raise
        } finally {
            $this->inHandler = false;
        }
    }

    private static function errnoLevel(int $errno): string
    {
        return match ($errno) {
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'info',
            default => 'error',
        };
    }
}

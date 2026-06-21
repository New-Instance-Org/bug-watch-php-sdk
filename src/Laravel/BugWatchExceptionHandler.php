<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use NewInstance\BugWatch\Client;

final class BugWatchExceptionHandler implements ExceptionHandler
{
    public function __construct(private readonly ExceptionHandler $inner, private readonly Client $client)
    {
    }

    public function report(\Throwable $e): void
    {
        try {
            if ($this->inner->shouldReport($e)) {
                $this->client->captureException($e, ['level' => 'error', 'tags' => ['handled' => 'true']]);
            }
        } catch (\Throwable) {
            // never let capture interfere with Laravel's own reporting
        }
        $this->inner->report($e);
    }

    public function shouldReport(\Throwable $e): bool
    {
        return $this->inner->shouldReport($e);
    }

    public function render($request, \Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        return $this->inner->render($request, $e);
    }

    public function renderForConsole($output, \Throwable $e): void
    {
        $this->inner->renderForConsole($output, $e);
    }
}

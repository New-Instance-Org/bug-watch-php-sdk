<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Transport;

use NewInstance\BugWatch\Config;

final class HttpTransport implements TransportInterface
{
    private const PATH = '/api/v1/bugwatch/ingest';
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    /** @var callable(string,list<string>,string):array{status:int} */
    private $sender;
    /** @var callable(int):void */
    private $sleeper;

    public function __construct(
        private readonly Config $config,
        private readonly RetryPolicy $retry,
        ?callable $sender = null,
        ?callable $sleeper = null,
    ) {
        $this->sender = $sender ?? $this->makeDefaultSender();
        $this->sleeper = $sleeper ?? static fn (int $ms): mixed => usleep($ms * 1000);
    }

    public function send(array $events): bool
    {
        if ($events === []) {
            return true;
        }

        $body = implode("\n", array_map(
            static fn (array $e): string => (string) json_encode($e, self::JSON_FLAGS),
            $events,
        ));
        $url = $this->config->endpoint . self::PATH;
        $headers = ['Content-Type: application/x-ndjson', 'x-api-key: ' . $this->config->projectKey];

        $attempts = $this->retry->maxAttempts();
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $res = ($this->sender)($body, $headers, $url);
                $status = (int) ($res['status'] ?? 0);
            } catch (\Throwable) {
                $status = 0;
            }

            if ($status >= 200 && $status < 300) {
                return true;
            }
            if (!$this->retry->shouldRetry($status)) {
                return false;
            }
            if ($i < $attempts - 1) {
                ($this->sleeper)($this->retry->delayMs($i));
            }
        }

        return false;
    }

    /** @return callable(string,list<string>,string):array{status:int} */
    private function makeDefaultSender(): callable
    {
        $timeout = $this->config->requestTimeout;
        $client = $this->config->httpClient;

        // PSR-18 client injected → use it (needs a PSR-17 factory; resolves Guzzle's, else falls through).
        $psr17 = $this->resolvePsr17();
        if ($client instanceof \Psr\Http\Client\ClientInterface && $psr17 !== null) {
            return static function (string $body, array $headers, string $url) use ($client, $psr17): array {
                $request = $psr17->createRequest('POST', $url)->withBody($psr17->createStream($body));
                foreach ($headers as $h) {
                    [$name, $value] = explode(':', $h, 2);
                    $request = $request->withHeader(trim($name), trim($value));
                }

                return ['status' => $client->sendRequest($request)->getStatusCode()];
            };
        }

        if (function_exists('curl_init')) {
            return static function (string $body, array $headers, string $url) use ($timeout): array {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT_MS => $timeout,
                    CURLOPT_CONNECTTIMEOUT_MS => $timeout,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);

                return ['status' => $status];
            };
        }

        // Stream-wrapper fallback (no ext-curl).
        return static function (string $body, array $headers, string $url) use ($timeout): array {
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout / 1000,
                'ignore_errors' => true,
            ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $stream = @fopen($url, 'r', false, $context);
            $status = 0;
            // PHP 8.5+ provides http_get_last_response_headers(); fall back to the magic variable on older PHP.
            $responseHeaders = function_exists('http_get_last_response_headers')
                ? (http_get_last_response_headers() ?? [])
                : (is_array($GLOBALS['http_response_header'] ?? null) ? $GLOBALS['http_response_header'] : []);
            foreach ($responseHeaders as $h) {
                if (preg_match('#HTTP/\S+\s+(\d{3})#', $h, $m)) {
                    $status = (int) $m[1];
                }
            }
            if ($stream !== false) {
                fclose($stream);
            }

            return ['status' => $status];
        };
    }

    private function resolvePsr17(): (\Psr\Http\Message\RequestFactoryInterface&\Psr\Http\Message\StreamFactoryInterface)|null
    {
        // Guzzle's HttpFactory implements both PSR-17 interfaces; used for the PSR-18 send + tests.
        // A later sub-project can add a `psr17Factory` config option. If no factory is available,
        // return null so the transport falls back to cURL/stream. (Parenthesized DNF type — PHP 8.2+.)
        if (class_exists(\GuzzleHttp\Psr7\HttpFactory::class)) {
            /** @var \Psr\Http\Message\RequestFactoryInterface&\Psr\Http\Message\StreamFactoryInterface */
            return new \GuzzleHttp\Psr7\HttpFactory();
        }

        return null;
    }
}

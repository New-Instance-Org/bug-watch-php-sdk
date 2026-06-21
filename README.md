# newinstance/bugwatch-php

One PHP SDK to capture logs, errors, and runtime context from any PHP app and send them to BugWatch.

> Create your account + project at **https://www.newinstance.cloud** to get a project key (`<keyId>:<secret>`).

## Install

    composer require newinstance/bugwatch-php

## Quick start

    use NewInstance\BugWatch\BugWatch;

    BugWatch::init([
        'projectKey' => getenv('BUGWATCH_KEY'), // "<keyId>:<secret>"
        'release'    => getenv('APP_VERSION'),  // optional
    ]);

    BugWatch::setUser(['id' => 'u_123', 'email' => 'alice@acme.com']);
    BugWatch::setTag('region', 'eu-west-1');

    try {
        doRiskyThing();
    } catch (\Throwable $e) {
        BugWatch::captureException($e);
    }

Isolated instances (multi-tenant): `use function NewInstance\BugWatch\createClient; $c = createClient(['projectKey' => ...]);`

`projectKey` is all you need — the SDK already targets BugWatch's hosted ingest API. The **environment** is
bound to your key server-side (use one key per environment); you never send it.

## Native PSR-3 logger

    $log = BugWatch::client()->getLogger(); // implements Psr\Log\LoggerInterface
    $log->error('payment gateway timeout {gateway}', ['gateway' => 'paystack']);
    $log->error('checkout failed', ['exception' => $e]); // a Throwable in context is captured

## Front-end apps (secure)

Never ship your `projectKey` to a browser. Mint a short-lived session token on your backend:

    use function NewInstance\BugWatch\mintBrowserSession;
    $session = mintBrowserSession(['projectKey' => getenv('BUGWATCH_KEY')]); // { token, expiresAt }

Return it to the browser and use the BugWatch JS SDK (`@newinstance/bugwatch`) there.

## Configuration

| Option | Default | Notes |
|---|---|---|
| `projectKey` | — (required) | `"<keyId>:<secret>"` — server-side only |
| `release` | — | Version string |
| `enabled` | `true` | `false` no-ops all capture |
| `debug` | `false` | Log SDK-internal diagnostics to error_log |
| `sampleRate` | `1.0` | 0–1 fraction kept |
| `sensitiveFields` | `[]` | Extra keys to redact (merged with defaults) |
| `maxQueueSize` | `1000` | In-memory buffer cap (drops oldest) |
| `batchSize` | `50` | Events per ingest request (≤5000) |
| `requestTimeout` | `15000` ms | Per-request timeout |
| `retry` | 3 / 200ms / 5s / ×2 | `RetryOptions` (exp backoff + jitter) |
| `httpClient` | — | Inject a PSR-18 client to reuse (else cURL/stream) |
| `beforeSend` | — | `fn(array $event): ?array` — drop (null) or mutate |

## Design guarantees

- **Never crashes your app** — every capture/serialize/redact/transport path is wrapped; internal errors go
  to diagnostics only, never thrown, never re-captured.
- **Redacted by default** — password/token/authorization/cookie/secret/apiKey/ssn/creditCard/cvv/… (add via
  `sensitiveFields`).
- **Reliable delivery** — bounded queue, NDJSON batching, exp-backoff retry with jitter, flush on shutdown
  (after `fastcgi_finish_request()` so the user's response isn't delayed under PHP-FPM).
- **Idempotent** — each event carries a stable id, so transport retries don't double-count server-side.

## Development

    composer install
    composer test   # phpunit
    composer stan   # phpstan
    composer cs     # php-cs-fixer (dry run)

## Monolog (your existing logger, unchanged)

    use Monolog\Logger;
    use NewInstance\BugWatch\Integration\Monolog\Handler;

    $log = new Logger('app');
    $log->pushHandler(new Handler(BugWatch::client())); // add alongside your other handlers
    $log->error('payment failed', ['exception' => $e]); // exceptions in context are captured

Works with Monolog 2 and 3. Keep all your existing handlers — BugWatch is just one more.

## Framework-less PHP (native handlers, opt-in)

    use NewInstance\BugWatch\Handlers\ErrorHandler;

    BugWatch::init(['projectKey' => getenv('BUGWATCH_KEY')]);
    ErrorHandler::install(BugWatch::client()); // uncaught exceptions + errors + fatal shutdowns

Handlers chain any previously-registered handler, respect `error_reporting()`, and never recurse.

## Laravel (auto-discovered)

1. `composer require newinstance/bugwatch-php`
2. Set `BUGWATCH_KEY` in `.env` (one key per environment).
3. Add the channel in `config/logging.php` (or add `bugwatch` to your `stack`):

       'bugwatch' => ['driver' => 'bugwatch'],

That's it. `Log::error(...)` (routed to the channel) and **uncaught exceptions** are captured automatically — no code changes. Optional: add `BugWatchContextMiddleware` to your HTTP kernel for request/user tags, and route the browser-session mint for front-end apps:

    use NewInstance\BugWatch\Laravel\Http\BrowserSessionController;
    Route::post('/bugwatch/session', BrowserSessionController::class);

Queue jobs, Artisan commands, and Octane requests flush + reset scope automatically. Disable auto exception capture with `BUGWATCH_CAPTURE_EXCEPTIONS=false`.

## License

MIT

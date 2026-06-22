<h1 align="center">newinstance/bugwatch-php</h1>

<p align="center">One PHP SDK to capture logs, errors, exceptions, and runtime context from any PHP app — and send them to BugWatch.</p>

<p align="center">
  <img alt="License: MIT" src="https://img.shields.io/badge/license-MIT-21bb42.svg" />
  <img alt="PHP: 8.2+" src="https://img.shields.io/badge/php-8.2%2B-787cb5.svg" />
  <img alt="PHPStan: max" src="https://img.shields.io/badge/phpstan-max-21bb42.svg" />
</p>

**One install. Every PHP framework and logger.** A framework-independent core plus first-class, tested
integrations for **Monolog** (2 & 3), **Laravel**, **PSR-3**, and **native PHP** error/exception/shutdown
capture — all from the same package. Keep using your existing logger; BugWatch receives events transparently.

```shell
composer require newinstance/bugwatch-php
```

> **New to BugWatch?** Create your account and a project at **[www.newinstance.cloud](https://www.newinstance.cloud)** — that's where you generate the `projectKey` (`"<keyId>:<secret>"`) used below.

> **Other languages?** JavaScript / TypeScript apps use **`@newinstance/bugwatch`** (npm). This package is PHP-only.

---

## Quick start

```php
use NewInstance\BugWatch\BugWatch;

BugWatch::init([
    'projectKey' => getenv('BUGWATCH_KEY'), // "<keyId>:<secret>" — server-side only
    'release'    => getenv('APP_VERSION'),  // optional
]);

BugWatch::setUser(['id' => 'u_123', 'email' => 'alice@acme.com']);
BugWatch::setTag('region', 'eu-west-1');

try {
    doRiskyThing();
} catch (\Throwable $e) {
    BugWatch::captureException($e);
}
```

`projectKey` is all the configuration you need — the SDK already points at BugWatch's hosted ingest API. The
**environment** (development / staging / production) is **bound to your key** server-side, so you never send
it; use one key per environment.

Need isolated instances (multi-tenant, advanced)? Use `createClient()` instead of the global singleton:

```php
use function NewInstance\BugWatch\createClient;

$client = createClient(['projectKey' => getenv('BUGWATCH_KEY')]);
$client->captureException($e);
```

---

## Capturing events

```php
BugWatch::captureException(\Throwable $e, array $hint = []): string;   // returns the event id
BugWatch::captureMessage(string $message, int|string $level = 'info'): string;
BugWatch::captureLog([
    'level'   => 'error',                 // name, Monolog int, or BugWatch numeric
    'message' => 'payment gateway timeout',
    'exception' => $e,                    // optional Throwable
    'tags'    => ['gateway' => 'paystack'],
    'user'    => ['id' => 'u_123'],
    'fingerprint' => 'optional-grouping-key',
]): string;
```

`$hint` for `captureException` accepts `level`, `tags`, and `user`. Levels use the BugWatch numerics
`trace 10 · debug 20 · info 30 · warn 40 · error 50 · fatal 60`, but you can also pass a **PSR-3 name**
(`'error'`) or a **Monolog integer** (`400`) anywhere a level is expected — they're normalized for you.

---

## Scope & context

Attach data once and it rides along with every subsequent event from the current client:

```php
BugWatch::setUser(['id' => 'u_123', 'email' => 'alice@acme.com']); // allow-listed: id, email, username, ip
BugWatch::setUser(null);                                            // clear
BugWatch::setTag('region', 'eu-west-1');
BugWatch::setTags(['plan' => 'pro', 'tenant' => 't_42']);
BugWatch::setContext('payment', ['provider' => 'paystack']);       // structured context
BugWatch::setRelease('checkout@2.4.1');
BugWatch::setFingerprint('manual-grouping-key');                   // string or string[]

// A temporary, isolated scope — mutations are discarded when the callback returns:
BugWatch::withScope(function ($scope) {
    $scope->tags['operation'] = 'checkout';
    BugWatch::captureMessage('checkout step failed', 'error');
});

// lifecycle
BugWatch::flush();   // send queued events now (returns bool: were all batches accepted?)
BugWatch::close();   // final flush (on shutdown)
```

> `setUser` only keeps the safe identifiers `id`, `email`, `username`, `ip`. Other fields are dropped, and
> sensitive keys are redacted everywhere (see [Privacy & redaction](#privacy--redaction)).

---

## Native PSR-3 logger

The SDK ships a first-class `Psr\Log\LoggerInterface` — perfect for apps with no logging library:

```php
$log = BugWatch::getLogger();                 // implements Psr\Log\LoggerInterface

$log->error('payment gateway timeout {gateway}', ['gateway' => 'paystack']); // {placeholders} interpolated
$log->warning('low inventory', ['sku' => 'A-12']);
$log->error('checkout failed', ['exception' => $e]);  // a Throwable in context is captured as an exception
```

Hand it to any library or framework that accepts a PSR-3 logger and you're done.

---

## Monolog (2 & 3) — your existing logger, unchanged

Most PHP apps (and Laravel, Symfony, Drupal, Magento, …) log through **Monolog**. Add BugWatch as one more
handler and keep every existing handler:

```php
use Monolog\Logger;
use NewInstance\BugWatch\Integration\Monolog\Handler;

$log = new Logger('payments');
$log->pushHandler(new Handler(BugWatch::client()));        // optional 2nd arg: minimum level (default 'debug')

$log->warning('cache miss', ['key' => 'u:1']);
$log->error('charge failed', ['exception' => $e]);          // exceptions in context become captured exceptions
```

Works with **Monolog 2** (array records) and **Monolog 3** (`LogRecord`) from the same class. The channel name
and any scalar `context` / `extra` values are forwarded as tags. The handler never throws into Monolog.

---

## Framework-less PHP — native handlers (opt-in)

Capture uncaught exceptions, PHP errors, and **fatal shutdowns** (the failures a `try/catch` can never see):

```php
use NewInstance\BugWatch\Handlers\ErrorHandler;

BugWatch::init(['projectKey' => getenv('BUGWATCH_KEY')]);
ErrorHandler::install(BugWatch::client());
// Fine-grained: ErrorHandler::install($client, ['errors' => true, 'exceptions' => true, 'shutdown' => true]);
```

The handlers **chain** any previously-registered handler, **respect `error_reporting()`** (including the `@`
operator), are recursion-guarded, and can be removed with `ErrorHandler::install(...)->uninstall()`. They are
opt-in by design — the SDK never installs global handlers behind your back.

---

## Laravel (auto-discovered)

The package auto-registers via Laravel package discovery — no provider to add.

```dotenv
# .env  (one key per environment)
BUGWATCH_KEY="<keyId>:<secret>"
```

```php
// config/logging.php — add the channel (or add 'bugwatch' to your 'stack')
'channels' => [
    'bugwatch' => ['driver' => 'bugwatch'],
],
```

That's it:

- **`Log::error(...)`** routed to the `bugwatch` channel is captured.
- **Uncaught exceptions** are captured automatically (Laravel's own reporting is untouched).
- **Queue jobs, Artisan commands, and Octane requests** flush and reset per-request scope automatically.

Publish the config to customise it:

```shell
php artisan vendor:publish --tag=bugwatch-config
```

Optional extras:

```php
// Request/route/user tags — add the middleware to your HTTP kernel or a route group:
\NewInstance\BugWatch\Laravel\BugWatchContextMiddleware::class

// Mint browser session tokens for your front-end (see "Front-end apps" below):
use NewInstance\BugWatch\Laravel\Http\BrowserSessionController;
Route::post('/bugwatch/session', BrowserSessionController::class);
```

Config keys (`config/bugwatch.php`): `key`, `endpoint`, `release`, `enabled`, `sample_rate`,
`sensitive_fields`, `capture_exceptions` (default `true`), `level`. Turn off automatic exception capture with
`BUGWATCH_CAPTURE_EXCEPTIONS=false`. Targets Laravel **11–13** (verified on 13).

---

## Front-end / browser apps (secure)

Browser code is public — **never put your `projectKey` in client-side code; it carries your secret.** Instead,
let your PHP backend hand the browser a short-lived, ingest-only **session token**:

```php
use function NewInstance\BugWatch\mintBrowserSession;

// In a backend route — the only place the secret lives:
$session = mintBrowserSession(['projectKey' => getenv('BUGWATCH_KEY')]); // ['token' => ..., 'expiresAt' => ...]
header('Content-Type: application/json');
echo json_encode($session);
```

(Laravel users can just route `BrowserSessionController` as shown above.) Return the JSON to the browser and
use the **`@newinstance/bugwatch`** JS SDK there with a `sessionUrl` — the secret never reaches the client.

---

## Long-running runtimes (Octane, queue workers, CLI loops)

In classic PHP-FPM each request is its own process, so scope can't leak. In **persistent** runtimes, reset
per-request state at each boundary:

```php
$client->flush();        // deliver what's queued
$client->resetScope();   // clear user/tags/context/release/fingerprint for the next unit of work
```

The Laravel integration does this for you on queue-job, command, and Octane-request boundaries. The default
delivery model is **buffer → flush on shutdown**, and under PHP-FPM the SDK calls `fastcgi_finish_request()`
first so your response is returned to the user *before* events are sent — no added request latency.

---

## Configuration

Pass to `BugWatch::init([...])` / `createClient([...])`:

| Option | Default | Notes |
|---|---|---|
| `projectKey` | — (required) | `"<keyId>:<secret>"`. **Server-side only.** Omit it (or set `enabled => false`) to no-op. |
| `endpoint` | `https://api.newinstance.cloud` | Ingest base URL (internal override). |
| `release` | — | Release / version string. |
| `enabled` | `true` | `false` no-ops all capture. |
| `debug` | `false` | Log SDK-internal diagnostics to `error_log`. |
| `sampleRate` | `1.0` | `0`–`1`; fraction of events kept. |
| `sensitiveFields` | `[]` | Extra keys to redact (merged with the built-in list). |
| `maxQueueSize` | `1000` | Bounded in-memory buffer; drops oldest on overflow. |
| `batchSize` | `50` | Events per ingest request (≤ 5000). |
| `flushInterval` | `0` | `0` = flush on shutdown/boundaries only (the PHP-FPM default). |
| `requestTimeout` | `15000` ms | Per-request timeout. |
| `retry` | 3 attempts · 200 ms → 5 s · ×2 + jitter | A `RetryOptions` instance. |
| `httpClient` | — | Inject a **PSR-18** client to reuse (Guzzle, Symfony HttpClient, …); else cURL, else streams. |
| `beforeSend` | — | `fn(array $event): ?array` — return `null` to drop, or a modified event. |

---

## Privacy & redaction

Redaction runs over every event **before** it's queued or serialized (and the server redacts again as
defense-in-depth). Default keys (case-insensitive) include: `password`, `token`, `authorization`, `cookie`,
`secret`, `apikey`, `clientsecret`, `sessionid`, `ssn`, `creditcard`, `cvv`, `pin`, `bvn`, `nin`, and more —
add your own with `sensitiveFields`. User context is limited to `id`, `email`, `username`, `ip`.

---

## Requirements

- **PHP 8.2+** (the supported floor; developed and verified on PHP 8.5 — a CI matrix across 8.2–8.5 is planned).
- Hard dependencies are minimal: `psr/log` and the PSR-7/17/18 HTTP interface packages.
- **Optional** (install only what you use):
  - `monolog/monolog` (`^2 || ^3`) — for the Monolog handler.
  - `laravel/framework` (`^11 || ^12 || ^13`) — for the Laravel integration (auto-discovered).
  - `ext-curl` — the default zero-config transport (a stream fallback is used if absent).

The core never pulls a framework in. Integrations activate only when their library is present.

---

## Framework & logger support

**First-class, tested integrations:** Monolog (2 & 3) · Laravel (11/12/13) · native PSR-3 logger · native PHP
error/exception/shutdown handlers · server-side browser-session mint.

**Everything else works through the universal core today** — any app that logs via **Monolog** or **PSR-3**
(Symfony, Slim, CakePHP, Drupal, Magento, …) is captured by attaching the Monolog handler or handing over the
PSR-3 logger; anywhere else, call `captureException()` / `captureLog()` directly. Dedicated one-line adapters
for more frameworks are on the roadmap.

---

## Design guarantees

- **Never crashes your app.** Every capture / serialize / redact / transport path is wrapped; internal errors
  go to the diagnostics logger only — never thrown into your app, never re-captured (no recursion).
- **Redacted by default** — sensitive keys are scrubbed before events leave your process.
- **Reliable, low-latency delivery** — bounded queue, NDJSON batching, exponential-backoff retry with jitter,
  flush on shutdown after `fastcgi_finish_request()`.
- **Idempotent** — each event carries a stable id, so transport retries never double-count server-side.
- **Strictly typed** — PHPStan at the **max** level with zero baseline; PSR-12.

---

## Development

```shell
composer install
composer test    # phpunit
composer stan    # phpstan (level max)
composer cs      # php-cs-fixer (dry run);  composer cs:fix to apply
```

## License

MIT

<h1 align="center">newinstance/bugwatch-php</h1>

<p align="center">One PHP SDK to capture logs, errors, exceptions, and runtime context from any PHP app — and send them to BugWatch.</p>

<p align="center">
  <img alt="License: MIT" src="https://img.shields.io/badge/license-MIT-21bb42.svg" />
  <img alt="PHP: 8.2+" src="https://img.shields.io/badge/php-8.2%2B-787cb5.svg" />
  <img alt="PHPStan: max" src="https://img.shields.io/badge/phpstan-max-21bb42.svg" />
</p>

**One install. Every PHP framework and logger.** A framework-independent core plus first-class, tested
integrations for **Laravel** (11/12/13), **Monolog** (2 & 3), **PSR-3**, and **native PHP**
error/exception/shutdown capture — all from the same package. Keep using your existing logger; BugWatch
receives events transparently.

---

## Table of contents

- [Requirements](#requirements)
- [Install](#install)
- [Where to get your project key](#where-to-get-your-project-key)
- [Environment setup](#environment-setup)
- [Basic initialisation](#basic-initialisation)
- [All configuration options](#all-configuration-options)
- [Capturing exceptions and messages](#capturing-exceptions-and-messages)
- [PSR-3 logger](#psr-3-logger)
- [Monolog (2 & 3)](#monolog-2--3)
- [Laravel](#laravel-auto-discovered)
- [Framework-less PHP — native error handlers](#framework-less-php--native-error-handlers)
- [Identifying users](#identifying-users)
- [Tags](#tags)
- [Context](#context)
- [Fingerprinting](#fingerprinting)
- [Release and environment](#release-and-environment)
- [Isolated scopes](#isolated-scopes-withscope--resetscope)
- [Privacy and redaction](#privacy-and-redaction)
- [Sampling](#sampling)
- [Browser / front-end apps (secure flow)](#browser--front-end-apps-secure-flow)
- [Long-running runtimes](#long-running-runtimes-octane-queue-workers-cli-loops)
- [Verification](#verification)
- [Production checklist](#production-checklist)
- [Troubleshooting](#troubleshooting)
- [Complete examples](#complete-examples)
- [Upgrade guide](#upgrade-guide)
- [Other languages](#other-languages)
- [Development](#development)
- [License](#license)

---

## Requirements

- **PHP 8.2 or higher** (developed and verified on PHP 8.5; a CI matrix across 8.2–8.5 is planned)
- Hard dependencies: `psr/log ^3`, `psr/http-client ^1`, `psr/http-factory ^1`, `psr/http-message ^1|^2`
- Optional:
  - `ext-curl` — the default zero-config HTTP transport (a stream fallback is used if absent)
  - `monolog/monolog ^2|^3` — for the Monolog handler
  - `laravel/framework ^11|^12|^13` — for the Laravel integration (auto-discovered)

---

## Install

```shell
composer require newinstance/bugwatch-php
```

The core never pulls a framework in. Integrations activate only when their library is present.

---

## Where to get your project key

1. Sign in at **[www.newinstance.cloud](https://www.newinstance.cloud)** and go to your organisation.
2. Open **BugWatch** and create (or open) a project.
3. In the project's **Settings → API Keys** tab, create a key with the `ingest:write` scope.
4. Copy the key in the format `<keyId>:<secret>` — this is your `projectKey`.

> **Keep this key server-side.** It carries your secret. Never put it in browser JavaScript, a mobile
> binary, or a public repository. See [Browser / front-end apps](#browser--front-end-apps-secure-flow)
> for the correct pattern when you need to send events from the browser.

You typically create **one key per environment** (development, staging, production). The environment is
bound to the key server-side, so you do not need to pass an `environment` field in the SDK.

---

## Environment setup

Store the key in your environment, never hard-coded:

```dotenv
# .env (or the environment of your server / CI)
BUGWATCH_KEY="<keyId>:<secret>"
APP_VERSION="1.3.2"         # optional — used as the release string
```

---

## Basic initialisation

### Global singleton (simplest)

Call `BugWatch::init()` once, early in your bootstrap file (e.g. `public/index.php` or `bootstrap/app.php`):

```php
use NewInstance\BugWatch\BugWatch;

BugWatch::init([
    'projectKey' => getenv('BUGWATCH_KEY'),
    'release'    => getenv('APP_VERSION'),   // optional
]);
```

After that, call `BugWatch::captureException(...)`, `BugWatch::captureMessage(...)`, etc., from anywhere in your app.

### Multiple isolated clients (`createClient`)

When you need separate configurations — for example in a multi-tenant app, or to send to different
projects — use `createClient()` instead of the global singleton:

```php
use function NewInstance\BugWatch\createClient;

$client = createClient(['projectKey' => getenv('BUGWATCH_KEY')]);
$client->captureException($e);
```

`createClient()` returns a `Client` instance with the same API as the static `BugWatch::*` facade.

---

## All configuration options

Pass any of these keys to `BugWatch::init([...])` or `createClient([...])`:

| Option | Type | Default | Description |
|---|---|---|---|
| `projectKey` | `string` | — (**required** unless `sessionUrl` set) | `"<keyId>:<secret>"`. **Server-side only.** |
| `sessionUrl` | `string` | `null` | Your backend route that mints a browser session token (see [browser flow](#browser--front-end-apps-secure-flow)). Used instead of `projectKey` when routing ingest through a session endpoint. |
| `endpoint` | `string` | `https://api.newinstance.cloud` | Ingest base URL. Change only when self-hosting or during local development. |
| `release` | `string` | `null` | Release / version string (e.g. `"2.4.1"`, `"abc1234"`). Shown in the dashboard and used for issue grouping. |
| `enabled` | `bool` | `true` | Set to `false` to no-op all capture (useful in tests). |
| `debug` | `bool` | `false` | Log SDK-internal diagnostics to `error_log`. Useful during initial setup. |
| `sampleRate` | `float` | `1.0` | Fraction of events to keep: `0.0` = drop all, `1.0` = keep all, `0.1` = keep 10%. |
| `sensitiveFields` | `string[]` | `[]` | Extra key names to redact (merged with the built-in list). |
| `maxQueueSize` | `int` | `1000` | Bounded in-memory buffer. Oldest events are dropped on overflow. |
| `batchSize` | `int` | `50` | Number of events per ingest request (1–5000). |
| `flushInterval` | `int` | `0` | Milliseconds between auto-flushes. `0` = flush only on shutdown / explicit call. |
| `requestTimeout` | `int` | `15000` | Per-request timeout in milliseconds. |
| `retry` | `RetryOptions` | 3 attempts, 200 ms → 5 s, ×2 + jitter | Pass a `RetryOptions` instance to customise retry behaviour. |
| `httpClient` | `object` | `null` | Inject a **PSR-18** HTTP client (e.g. Guzzle, Symfony HttpClient). If omitted, cURL is used, falling back to PHP streams. |
| `beforeSend` | `callable` | `null` | `fn(array $event): ?array` — called before each event is queued. Return `null` to drop the event, or return the (optionally modified) event to send it. |

---

## Capturing exceptions and messages

```php
// Capture a Throwable — returns the event ID string
$eventId = BugWatch::captureException($e);

// Capture with extra hints
$eventId = BugWatch::captureException($e, [
    'level' => 'fatal',               // override the default 'error' level
    'tags'  => ['component' => 'checkout'],
    'user'  => ['id' => 'u_123'],
]);

// Capture a plain message
BugWatch::captureMessage('Payment gateway timed out', 'warn');

// Capture a structured log event
BugWatch::captureLog([
    'level'       => 'error',
    'message'     => 'Order creation failed',
    'exception'   => $e,             // optional — attaches the exception stacktrace
    'tags'        => ['order_id' => '1234', 'gateway' => 'paystack'],
    'user'        => ['id' => 'u_123', 'email' => 'alice@acme.com'],
    'fingerprint' => 'order-creation-failure',   // optional grouping key
]);
```

### Severity levels

Levels are normalised from any of three formats — you can mix them freely:

| BugWatch numeric | PSR-3 / string name | Monolog integer |
|---|---|---|
| `10` | `'trace'` | — |
| `20` | `'debug'` | `100` |
| `30` | `'info'` / `'notice'` | `200` |
| `40` | `'warn'` / `'warning'` | `300` |
| `50` | `'error'` | `400` |
| `60` | `'fatal'` / `'critical'` / `'alert'` / `'emergency'` | `500+` |

---

## PSR-3 logger

The SDK ships a full `Psr\Log\LoggerInterface` — ideal for apps that have no logging library, or for
passing to any third-party library that accepts a PSR-3 logger:

```php
$log = BugWatch::getLogger();  // returns Psr\Log\LoggerInterface

// Standard PSR-3 levels. {placeholders} in the message are interpolated from context.
$log->info('User {user} signed in', ['user' => 'alice']);
$log->warning('Cache miss for key {key}', ['key' => 'u:1']);
$log->error('Charge failed', ['exception' => $e]);  // a Throwable in context becomes a captured exception
$log->critical('Database connection lost');

// On a named createClient() instance:
$logger = $client->getLogger();
```

---

## Monolog (2 & 3)

Most PHP applications (Laravel, Symfony, Slim, Drupal, Magento, …) log through **Monolog**. Add BugWatch
as one more handler and keep every existing handler:

```php
use Monolog\Logger;
use NewInstance\BugWatch\Integration\Monolog\Handler;

// Attach to an existing Monolog logger
$log = new Logger('payments');
$log->pushHandler(new Handler(BugWatch::client()));
// Optional: set a minimum level (default 'debug' = captures everything Monolog passes to this handler)
$log->pushHandler(new Handler(BugWatch::client(), 'warning'));

// Use normally — BugWatch receives every record at or above the handler's minimum level
$log->warning('Cache miss', ['key' => 'u:1']);
$log->error('Charge failed', ['exception' => $e]);   // exception in context → captured exception in BugWatch
```

The handler is compatible with **Monolog 2** (array records) and **Monolog 3** (`LogRecord`) from the same
class. The Monolog channel name and any scalar `context` / `extra` values are forwarded as tags. The handler
never throws into Monolog.

---

## Laravel (auto-discovered)

The `BugWatchServiceProvider` is registered automatically via Laravel package discovery. No manual
provider registration is needed.

### 1. Set your environment variables

```dotenv
# .env
BUGWATCH_KEY="<keyId>:<secret>"

# Optional
BUGWATCH_RELEASE="1.3.2"
BUGWATCH_ENABLED=true
BUGWATCH_SAMPLE_RATE=1.0
BUGWATCH_CAPTURE_EXCEPTIONS=true   # set false to disable automatic exception capture
BUGWATCH_LEVEL="debug"             # minimum log level forwarded to BugWatch via the log channel
# BUGWATCH_ENDPOINT=https://api.newinstance.cloud  # only needed for local/self-hosted overrides
```

### 2. Publish the config (optional, to customise)

```shell
php artisan vendor:publish --tag=bugwatch-config
```

This copies `config/bugwatch.php` into your project so you can edit `sensitive_fields` and other settings
directly. The published file maps every env variable listed above.

### 3. Add the log channel

In `config/logging.php`, add the `bugwatch` driver to your channels. You can use it standalone or add it
to an existing `stack`:

```php
// config/logging.php
'channels' => [
    // ... your existing channels ...

    // BugWatch standalone channel:
    'bugwatch' => [
        'driver' => 'bugwatch',
    ],

    // Or add 'bugwatch' to a stack alongside your existing channel:
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['single', 'bugwatch'],
    ],
],
```

### 4. What you get automatically

Once the above is in place, Laravel gives you:

- **Unhandled exceptions** captured automatically — Laravel's own exception reporting is untouched.
- **`Log::error(...)` / `Log::warning(...)` etc.** routed to the `bugwatch` channel are captured.
- **Queue jobs** flush and reset per-job scope automatically (both `JobProcessed` and `JobFailed`).
- **Artisan commands** flush at command completion.
- **Laravel Octane** flushes and resets scope at each request termination.

### 5. Optional extras

**Request/route/user context middleware** — adds `method`, `url`, `route`, and the authenticated user ID
to every event from that request:

```php
// In app/Http/Kernel.php, add to the $middleware array or a specific route group:
\NewInstance\BugWatch\Laravel\BugWatchContextMiddleware::class,
```

The middleware also calls `flush()` + `resetScope()` at request termination, so events are delivered
before the response lands and scope is clean for the next request.

**Browser session minting** — registers a route your JavaScript front-end can call to get a short-lived
session token (see [browser flow](#browser--front-end-apps-secure-flow)):

```php
// routes/web.php (or routes/api.php)
use NewInstance\BugWatch\Laravel\Http\BrowserSessionController;

Route::post('/bugwatch/session', BrowserSessionController::class)
    ->middleware('auth');  // protect with your auth middleware
```

---

## Framework-less PHP — native error handlers

Capture uncaught exceptions, PHP errors (`E_WARNING`, `E_NOTICE`, etc.), and **fatal shutdowns** — the
failures that `try/catch` can never see:

```php
use NewInstance\BugWatch\Handlers\ErrorHandler;

BugWatch::init(['projectKey' => getenv('BUGWATCH_KEY')]);
ErrorHandler::install(BugWatch::client());
```

Fine-grained control over which handlers are installed:

```php
// Install only the exception handler, for example:
ErrorHandler::install($client, [
    'exceptions' => true,
    'errors'     => false,
    'shutdown'   => true,
]);

// Remove the handlers later (note: shutdown functions cannot be removed in PHP;
// the shutdown handler becomes a no-op unless a fatal error occurred)
$handler = ErrorHandler::install($client);
$handler->uninstall();
```

The handlers **chain** any previously-registered handler, **respect `error_reporting()`** (including the
`@` operator), are recursion-guarded, and are **opt-in by design** — the SDK never installs global
handlers behind your back.

---

## Identifying users

Attach user identity once and it rides along with every subsequent event:

```php
BugWatch::setUser([
    'id'       => 'u_123',          // required — your internal user identifier
    'email'    => 'alice@acme.com', // optional
    'username' => 'alice',          // optional
    'ip'       => $request->ip(),   // optional
]);

// Clear user identity (e.g. on logout):
BugWatch::setUser(null);
```

Only the allow-listed fields `id`, `email`, `username`, and `ip` are kept. Any other keys are dropped
before the event leaves your process.

---

## Tags

Tags are key/value strings used for filtering and searching in the dashboard:

```php
BugWatch::setTag('region', 'eu-west-1');
BugWatch::setTag('plan', 'pro');

// Set several at once:
BugWatch::setTags(['tenant' => 't_42', 'version' => '2.4.1']);
```

Only scalar values are accepted. Booleans are normalised to `'true'`/`'false'`.

---

## Context

Context accepts arbitrary structured data (nested arrays, objects) and is stored alongside the event for
inspection in the dashboard:

```php
BugWatch::setContext('payment', [
    'provider'   => 'paystack',
    'reference'  => 'REF_9zX1k',
    'amount_ngn' => 5000,
]);

BugWatch::setContext('server', [
    'hostname' => gethostname(),
    'php'      => PHP_VERSION,
]);
```

---

## Fingerprinting

Override BugWatch's default grouping algorithm to control how events are grouped into issues:

```php
// Group all "payment timeout" events into one issue, regardless of stack trace:
BugWatch::setFingerprint('payment-gateway-timeout');

// Multi-part fingerprint — group by component + error code:
BugWatch::setFingerprint(['checkout', 'GATEWAY_TIMEOUT']);
```

You can also pass `fingerprint` inside `captureLog()` for per-event overrides.

---

## Release and environment

The release string lets you correlate issues with the version of your code that introduced them:

```php
BugWatch::init([
    'projectKey' => getenv('BUGWATCH_KEY'),
    'release'    => getenv('APP_VERSION'),   // e.g. "1.3.2" or a git SHA
]);

// Or update it mid-run:
BugWatch::setRelease('checkout@2.4.1');
```

The **environment** (development / staging / production) is bound to your API key server-side — you do
not send it in the SDK. Create one key per environment.

---

## Isolated scopes (`withScope` / `resetScope`)

`withScope` opens a temporary scope. Any tags, user, context, or fingerprint set inside the callback are
discarded when it returns. The outer scope is untouched:

```php
BugWatch::setTag('tenant', 't_global');

BugWatch::withScope(function ($scope) {
    $scope->tags['operation'] = 'checkout';
    $scope->user = ['id' => 'u_999'];
    BugWatch::captureMessage('Checkout step failed', 'error');
    // 'tenant' = 't_global' is inherited from the outer scope
});

// After the callback: 'operation' and the scoped user are gone; 'tenant' is still 't_global'
```

In persistent runtimes, clear all scope state at request or job boundaries:

```php
$client->flush();        // send any queued events
$client->resetScope();   // clear user / tags / context / release / fingerprint
```

The Laravel integration calls this for you on queue-job, Artisan-command, and Octane-request boundaries.

---

## Privacy and redaction

Redaction runs over every event **before** it is queued or serialised. The server redacts again as
defence-in-depth.

**Built-in redacted keys** (case-insensitive, partial-match): `password`, `token`, `authorization`,
`cookie`, `secret`, `apikey`, `clientsecret`, `sessionid`, `ssn`, `creditcard`, `cvv`, `pin`, `bvn`,
`nin`, and more.

Add your own:

```php
BugWatch::init([
    'projectKey'      => getenv('BUGWATCH_KEY'),
    'sensitiveFields' => ['otp', 'national_id', 'card_number'],
]);
```

**User context** is restricted to `id`, `email`, `username`, and `ip`. All other user fields are dropped.

**`beforeSend`** gives you full control over every event before it is sent — inspect, modify, or drop:

```php
BugWatch::init([
    'projectKey' => getenv('BUGWATCH_KEY'),
    'beforeSend' => function (array $event): ?array {
        // Drop health-check noise
        if (str_contains($event['message'] ?? '', 'healthcheck')) {
            return null;
        }
        // Scrub a custom field
        if (isset($event['tags']['internal_ref'])) {
            unset($event['tags']['internal_ref']);
        }
        return $event;
    },
]);
```

---

## Sampling

Reduce ingest volume by capturing only a fraction of events:

```php
BugWatch::init([
    'projectKey' => getenv('BUGWATCH_KEY'),
    'sampleRate' => 0.25,   // keep 25% of events; the other 75% are silently dropped
]);
```

Events that are dropped by sampling still return a stable event ID so your error-boundary UI can
display it — they just never reach the server.

---

## Browser / front-end apps (secure flow)

Your PHP `projectKey` carries a secret. **Never put it in browser JavaScript, client-side code, or a
mobile binary.** Instead, your PHP backend mints a short-lived **session token** and passes it to the
browser. The secret stays on the server.

### How it works

1. Your browser calls a **backend endpoint** (e.g. `POST /bugwatch/session`).
2. That endpoint calls `mintBrowserSession(...)` with your `projectKey`.
3. BugWatch mints a short-lived, project-scoped token via `POST /api/v1/bugwatch/browser-session`.
4. The token is returned to the browser as `{ token, expiresAt }`.
5. The JavaScript SDK uses that token via `sessionUrl` — the secret never reaches the client.

### Vanilla PHP / Slim / any framework

```php
use function NewInstance\BugWatch\mintBrowserSession;

// In your backend route handler:
$session = mintBrowserSession([
    'projectKey' => getenv('BUGWATCH_KEY'),
    // 'endpoint' => 'https://api.newinstance.cloud',  // optional override
]);
// $session = ['token' => '...', 'expiresAt' => 1735000000]

header('Content-Type: application/json');
echo json_encode($session);
```

### Laravel

Register `BrowserSessionController` as a route (see the [Laravel section](#laravel-auto-discovered)):

```php
// routes/web.php
use NewInstance\BugWatch\Laravel\Http\BrowserSessionController;

Route::post('/bugwatch/session', BrowserSessionController::class)
    ->middleware('auth');
```

The controller reads `BUGWATCH_KEY` from the published config automatically.

### JavaScript SDK side (pairing)

In your browser JavaScript, configure the `@newinstance/bugwatch` SDK with `sessionUrl` pointing at
your backend route. The JS SDK calls that route automatically to get a fresh token:

```js
import BugWatch from '@newinstance/bugwatch/browser';

BugWatch.init({
    sessionUrl: '/bugwatch/session',   // your backend route — NOT the projectKey
    environment: 'production',
    release: '1.3.2',
});
```

The JS SDK sends events to `/api/v1/bugwatch/ingest/browser` using the short-lived `x-bugwatch-session`
token. Your `projectKey` / secret is never in the browser.

---

## Long-running runtimes (Octane, queue workers, CLI loops)

In classic PHP-FPM each request is its own process, so scope cannot leak. In **persistent** runtimes
(Laravel Octane, queue workers, long-running CLI scripts), reset scope at each unit-of-work boundary:

```php
// At the end of each request / job / iteration:
$client->flush();       // deliver queued events
$client->resetScope();  // clear user / tags / context for the next unit
```

The Laravel integration does this automatically for Octane requests, queue jobs, and Artisan commands.

**PHP-FPM delivery note:** the default delivery model is buffer → flush on shutdown. Under PHP-FPM the
SDK calls `fastcgi_finish_request()` before flushing, so the response is returned to the user _before_
events are sent. No added request latency.

---

## Verification

After installing and initialising, confirm events are reaching BugWatch:

```php
// 1. Enable debug mode temporarily
BugWatch::init([
    'projectKey' => getenv('BUGWATCH_KEY'),
    'debug'      => true,
]);

// 2. Send a test event
$eventId = BugWatch::captureMessage('BugWatch PHP SDK test', 'info');
BugWatch::flush();

// 3. Check error_log for "[BugWatch]" lines — they show transport activity
// 4. Open the BugWatch dashboard → your project → Logs/Issues and look for the test event
echo "Event ID: {$eventId}\n";
```

You can also inspect the internal counters:

```php
$diag = BugWatch::client()->diagnostics();
// $diag exposes internal counters; in debug mode it writes to error_log
```

---

## Production checklist

- [ ] `BUGWATCH_KEY` is set in your production environment (not in code).
- [ ] One key per environment (production key never used in development).
- [ ] `debug` is `false` (the default) in production.
- [ ] `release` is set (a version string or git SHA) so you can correlate issues with deploys.
- [ ] `sensitiveFields` includes any domain-specific PII beyond the built-in list.
- [ ] For long-running processes (Octane, workers): `flush()` + `resetScope()` at every boundary.
- [ ] `BrowserSessionController` (or equivalent) is protected by an auth middleware.
- [ ] `sampleRate` is tuned if you have very high event volume.
- [ ] `ext-curl` is available (verify with `php -m | grep curl`).

---

## Troubleshooting

**Events are not appearing in the dashboard**

1. Set `debug => true` and check `error_log` for `[BugWatch]` entries.
2. Call `BugWatch::flush()` explicitly and look for transport errors.
3. Verify `BUGWATCH_KEY` is a valid `<keyId>:<secret>` string (`sk_test_...` or `sk_live_...`).
4. Check that `enabled` is not set to `false`.
5. Check `sampleRate` — if it is `0.0`, all events are dropped.

**Laravel: no events despite `Log::error(...)` calls**

- Confirm the `bugwatch` channel is in your `config/logging.php` and your `LOG_CHANNEL` points to it
  (or to a stack that includes it).
- Run `php artisan config:clear` after changing `.env` values.
- Check that `BUGWATCH_KEY` is set and `BUGWATCH_ENABLED` is not `false`.

**`BugWatch: "projectKey" (or "sessionUrl") is required.` exception**

- You called `BugWatch::init([...])` without a `projectKey` (or `sessionUrl`) and `enabled` is `true`.
- In Laravel this is suppressed — the SDK self-disables when no key is configured. Enable `debug` to see
  the log message.

**Monolog handler receives records but events don't appear**

- Confirm `BugWatch::init([...])` was called before the `Handler` was pushed.
- The handler's minimum level filters incoming records. Default is `'debug'`; raise it if needed.

**`mintBrowserSession` throws `BugWatch: failed to mint browser session (HTTP 401)`**

- The `projectKey` you passed does not have the `ingest:write` scope, or it is invalid.
- Verify the key in the dashboard under **API Keys**.

**High memory usage in workers**

- `maxQueueSize` (default `1000`) bounds the in-memory event buffer. Events are dropped (oldest first)
  when the buffer is full. Lower it if needed.
- Call `flush()` more frequently to keep the buffer small.

---

## Complete examples

### Minimal vanilla PHP

```php
<?php
// public/index.php (or your bootstrap file)

require __DIR__ . '/../vendor/autoload.php';

use NewInstance\BugWatch\BugWatch;
use NewInstance\BugWatch\Handlers\ErrorHandler;

// 1. Initialise
BugWatch::init([
    'projectKey' => getenv('BUGWATCH_KEY'),
    'release'    => getenv('APP_VERSION'),
]);

// 2. Install native handlers (optional — captures uncaught exceptions + E_ERROR fatals)
ErrorHandler::install(BugWatch::client());

// 3. Identify the current user (if you have one)
BugWatch::setUser(['id' => 'u_123', 'email' => 'alice@acme.com']);
BugWatch::setTag('region', 'eu-west-1');

// 4. Capture events
try {
    processPayment($order);
} catch (\Throwable $e) {
    BugWatch::captureException($e, ['tags' => ['order_id' => $order->id]]);
    http_response_code(500);
    echo json_encode(['error' => 'Payment failed']);
    exit;
}

// flush() is called automatically on shutdown via register_shutdown_function
```

### Realistic Laravel application

```php
// config/logging.php — add the channel
'channels' => [
    'stack'    => ['driver' => 'stack', 'channels' => ['daily', 'bugwatch']],
    'bugwatch' => ['driver' => 'bugwatch'],
],
```

```php
// app/Http/Kernel.php — add context middleware to the web group
protected $middlewareGroups = [
    'web' => [
        // ... existing middleware ...
        \NewInstance\BugWatch\Laravel\BugWatchContextMiddleware::class,
    ],
];
```

```php
// routes/web.php — browser session endpoint (for your JS front-end)
use NewInstance\BugWatch\Laravel\Http\BrowserSessionController;

Route::post('/bugwatch/session', BrowserSessionController::class)
    ->middleware('auth');
```

```php
// app/Jobs/ProcessPayment.php — explicit scope reset in a long-running worker
public function handle(): void
{
    try {
        BugWatch::setTag('order_id', $this->orderId);
        BugWatch::setUser(['id' => $this->userId]);
        // ... process payment ...
    } catch (\Throwable $e) {
        Log::channel('bugwatch')->error('Payment job failed', ['exception' => $e]);
        throw $e;
    }
    // Laravel integration auto-flushes + resets scope after each job
}
```

```dotenv
# .env
BUGWATCH_KEY="sk_live_xxxx:yyyy"
BUGWATCH_RELEASE="1.3.2"
LOG_CHANNEL=stack
```

---

## Upgrade guide

### 0.1.x — initial release

No migration needed.

---

## Other languages

| Platform | Package |
|---|---|
| JavaScript / TypeScript | [`@newinstance/bugwatch`](https://www.npmjs.com/package/@newinstance/bugwatch) on npm |
| iOS (Swift) | `BugWatch` — pod or Swift Package Manager (see BugWatch dashboard → Setup) |
| Android (Kotlin/Java) | `cloud.newinstance:bugwatch` on Maven Central |
| Flutter | `bugwatch` (see BugWatch dashboard → Setup) |
| React Native | [`@newinstance/bugwatch-react-native`](https://www.npmjs.com/package/@newinstance/bugwatch-react-native) on npm |
| CLI (symbol upload) | `@newinstance/bugwatch-cli` |

---

## Development

```shell
composer install
composer test     # phpunit
composer stan     # phpstan (level max)
composer cs       # php-cs-fixer dry run
composer cs:fix   # apply php-cs-fixer fixes
```

---

## License

MIT

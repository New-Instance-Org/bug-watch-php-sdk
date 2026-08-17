# Changelog

All notable changes to `newinstance/bugwatch-php` are documented here.

---

## Unreleased

### Added

- **Distributed tracing.** `BugWatch::startSpan()` / `BugWatch::withSpan()` create W3C/OTel-compatible
  spans (kind, attributes, exception events with stacktraces, error status, span links) exported as
  OTLP JSON to the platform traces endpoint; `Span::traceparent()` and `BugWatch::traceHeaders()`
  propagate the context to downstream services. New `serviceName` config option (also mapped from
  `BUGWATCH_SERVICE_NAME` by the Laravel provider) names the service on the BugWatch service map.
- **Trace context on events.** `BugWatch::setTraceContext()` / `getTraceContext()`, strict
  `TraceContext::parseTraceparent()` / `buildTraceparent()` helpers, per-capture `traceId`/`spanId`
  hints on `captureException`, and `traceId`/`spanId` emitted on the wire so errors and logs join
  the request's trace.
- **Laravel middleware reads `traceparent`.** `BugWatchContextMiddleware` joins the caller's trace
  automatically on every request that carries the header.

- **Configurable user resolver for the Laravel middleware** —
  `BugWatchContextMiddleware::resolveUserUsing(fn (Request $r) => ['id' => ..., 'email' => ...])`,
  registered once from a service provider's `boot()`. The middleware previously attached only the
  default guard's user id; a resolver lets you attach email/name/tenant or read a custom guard. It
  runs inside a guard (a throwing resolver never affects the request) and returning `null` attaches
  no user. Register it in `boot()` rather than config so `config:cache` keeps working.

---

## 0.1.0 — 2026-06-21

Initial public release.

### Added

- `BugWatch::init([...])` global singleton and `createClient([...])` for isolated instances.
- `Client` with `captureException`, `captureMessage`, `captureLog`, `setUser`, `setTag`, `setTags`,
  `setContext`, `setRelease`, `setFingerprint`, `withScope`, `resetScope`, `getLogger`, `flush`, `close`,
  `diagnostics`.
- PSR-3 `Logger` (`getLogger()`) with `{placeholder}` interpolation and `exception`-in-context support.
- **Monolog handler** (`NewInstance\BugWatch\Integration\Monolog\Handler`) compatible with Monolog 2 and 3.
- **Laravel integration** (`BugWatchServiceProvider`, auto-discovered):
  - `bugwatch` log channel driver.
  - Automatic exception capture via `BugWatchExceptionHandler`.
  - Per-job and per-request `flush()` + `resetScope()` (queue, Artisan, Octane).
  - `BugWatchContextMiddleware` for request/route/user tagging.
  - `BrowserSessionController` for minting browser session tokens.
  - Config: `vendor:publish --tag=bugwatch-config` → `config/bugwatch.php`.
  - Env keys: `BUGWATCH_KEY`, `BUGWATCH_ENDPOINT`, `BUGWATCH_RELEASE`, `BUGWATCH_ENABLED`,
    `BUGWATCH_SAMPLE_RATE`, `BUGWATCH_CAPTURE_EXCEPTIONS`, `BUGWATCH_LEVEL`.
- **Native PHP error handlers** (`ErrorHandler::install`) covering uncaught exceptions, PHP errors,
  and fatal shutdowns. Chain-safe, `error_reporting`-aware, recursion-guarded.
- **Secure browser flow** — `mintBrowserSession(['projectKey' => ..., 'endpoint' => ...])` mints a
  short-lived session token via `POST /api/v1/bugwatch/browser-session`; secret never reaches the browser.
- `Config::fromArray` with full validation and `RetryOptions` for configurable retry behaviour.
- `HttpTransport` with cURL primary, stream fallback, NDJSON batching, exponential-backoff retry with jitter.
- In-process event queue with configurable `maxQueueSize` and bounded overflow (oldest-first drop).
- Redaction pipeline (built-in sensitive key list + custom `sensitiveFields`; `beforeSend` hook).
- Sampling via `sampleRate` (0.0–1.0).
- PSR-18 injectable `httpClient` option.
- `Sdk::NAME = 'newinstance/bugwatch-php'`, `Sdk::VERSION = '0.1.0'`.
- PHPStan max level, zero baseline. PSR-12 code style.
- PHPUnit test suite (55 tests).
- Packagist: `newinstance/bugwatch-php ^0.1`.

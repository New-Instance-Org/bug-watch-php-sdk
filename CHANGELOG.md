# Changelog

All notable changes to `newinstance/bugwatch-php` are documented here.

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

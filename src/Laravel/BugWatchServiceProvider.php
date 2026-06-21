<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use Monolog\Level as MonologLevel;
use Monolog\Logger as MonologLogger;
use NewInstance\BugWatch\Client;
use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Integration\Monolog\Handler;

final class BugWatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/bugwatch.php', 'bugwatch');

        $this->app->singleton(Client::class, function (Application $app): Client {
            /** @var \Illuminate\Config\Repository $config */
            $config = $app->make('config');
            /** @var array<string,mixed> $cfg */
            $cfg = $config->get('bugwatch', []);
            $options = ['enabled' => (bool) ($cfg['enabled'] ?? true)];
            if (is_string($cfg['key'] ?? null) && $cfg['key'] !== '') {
                $options['projectKey'] = $cfg['key'];
            }
            if (is_string($cfg['endpoint'] ?? null)) {
                $options['endpoint'] = $cfg['endpoint'];
            }
            if (is_string($cfg['release'] ?? null)) {
                $options['release'] = $cfg['release'];
            }
            $options['sampleRate'] = is_numeric($cfg['sample_rate'] ?? null) ? (float) $cfg['sample_rate'] : 1.0;
            $options['sensitiveFields'] = is_array($cfg['sensitive_fields'] ?? null) ? $cfg['sensitive_fields'] : [];
            // No projectKey configured → disable rather than crash the app.
            if (!isset($options['projectKey'])) {
                $options['enabled'] = false;
            }

            return new Client(Config::fromArray($options));
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/config/bugwatch.php' => $this->app->configPath('bugwatch.php')], 'bugwatch-config');

        // Register the "bugwatch" log channel driver: a Monolog logger wrapping our handler.
        // Note: LogManager::extend() rebinds the closure to itself, so we must NOT use self:: inside
        // the closure — extract the helper as a static fn captured by the closure instead.
        $resolveLevel = static function (string $name): MonologLevel {
            return match (strtolower($name)) {
                'debug' => MonologLevel::Debug,
                'info' => MonologLevel::Info,
                'notice' => MonologLevel::Notice,
                'warning' => MonologLevel::Warning,
                'error' => MonologLevel::Error,
                'critical' => MonologLevel::Critical,
                'alert' => MonologLevel::Alert,
                'emergency' => MonologLevel::Emergency,
                default => MonologLevel::Debug,
            };
        };

        /** @var LogManager $logManager */
        $logManager = $this->app->make('log');
        $logManager->extend('bugwatch', function (Application $app, array $config) use ($resolveLevel): MonologLogger {
            /** @var \Illuminate\Config\Repository $repository */
            $repository = $app->make('config');
            $raw = $config['level'] ?? $repository->get('bugwatch.level', 'debug');
            $level = $resolveLevel(is_string($raw) ? $raw : 'debug');

            return new MonologLogger('bugwatch', [new Handler($app->make(Client::class), $level)]);
        });
    }
}

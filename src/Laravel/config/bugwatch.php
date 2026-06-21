<?php

declare(strict_types=1);

$sampleRate = env('BUGWATCH_SAMPLE_RATE', 1.0);

return [
    'key' => env('BUGWATCH_KEY'),
    'endpoint' => env('BUGWATCH_ENDPOINT', 'https://api.newinstance.cloud'),
    'release' => env('BUGWATCH_RELEASE'),
    'enabled' => env('BUGWATCH_ENABLED', true),
    'sample_rate' => is_numeric($sampleRate) ? (float) $sampleRate : 1.0,
    'sensitive_fields' => [],
    'capture_exceptions' => env('BUGWATCH_CAPTURE_EXCEPTIONS', true),
    'level' => env('BUGWATCH_LEVEL', 'debug'),
];

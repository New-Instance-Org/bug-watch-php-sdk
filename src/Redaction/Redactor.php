<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Redaction;

final class Redactor
{
    private const MAX_DEPTH = 8;
    private const MAX_NODES = 5000;

    /** @var list<string> */
    private const DEFAULTS = [
        'password', 'passwd', 'pwd', 'token', 'accesstoken', 'refreshtoken', 'idtoken',
        'authorization', 'auth', 'cookie', 'setcookie', 'secret', 'clientsecret', 'apikey',
        'privatekey', 'sessionid', 'ssn', 'creditcard', 'cardnumber', 'pan', 'cvv', 'pin', 'nin', 'bvn',
    ];

    /** @var array<string,true> */
    private array $keys = [];

    private int $nodes = 0;

    /** @param list<string> $extraKeys */
    public function __construct(array $extraKeys = [])
    {
        foreach ([...self::DEFAULTS, ...array_map('strtolower', $extraKeys)] as $k) {
            $this->keys[$k] = true;
        }
    }

    public function redact(mixed $value): mixed
    {
        $this->nodes = 0;

        return $this->walk($value, 0);
    }

    private function walk(mixed $value, int $depth): mixed
    {
        if ($depth >= self::MAX_DEPTH || !is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $val) {
            if (++$this->nodes > self::MAX_NODES) {
                $out[$key] = '[TRUNCATED]';
                continue;
            }
            $lower = is_string($key) ? strtolower($key) : null;
            $out[$key] = ($lower !== null && isset($this->keys[$lower]))
                ? '[REDACTED]'
                : $this->walk($val, $depth + 1);
        }

        return $out;
    }
}

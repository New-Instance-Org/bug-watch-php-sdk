<?php

declare(strict_types=1);

namespace NewInstance\BugWatch;

use NewInstance\BugWatch\Context\Scope;
use NewInstance\BugWatch\Redaction\Redactor;
use NewInstance\BugWatch\Serializer\ExceptionSerializer;

final class Normalizer
{
    private const USER_KEYS = ['id', 'email', 'username', 'ip'];

    public function __construct(private readonly Config $config, private readonly Redactor $redactor)
    {
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function build(array $input, Scope $scope): array
    {
        $event = [
            'eventId' => is_string($input['eventId'] ?? null) ? $input['eventId'] : EventId::generate(),
            'time' => (int) round(microtime(true) * 1000),
            'level' => Severity::toNumber($input['level'] ?? 'info'),
            'sdk' => ['name' => Sdk::NAME, 'version' => Sdk::VERSION],
        ];

        if (isset($input['message'])) {
            $event['message'] = (string) $input['message'];
        }

        if (($input['exception'] ?? null) instanceof \Throwable) {
            $e = $input['exception'];
            $event['exception'] = ExceptionSerializer::serialize($e);
            $event['message'] ??= ExceptionSerializer::serialize($e)['value'];
            $causes = ExceptionSerializer::causes($e);
            if ($causes !== []) {
                $event['causes'] = $causes;
            }
        }

        $tags = array_merge($scope->tags, self::stringMap((array) ($input['tags'] ?? [])));
        if ($tags !== []) {
            $event['tags'] = $tags;
        }

        $user = array_merge($scope->user, self::userPick((array) ($input['user'] ?? [])));
        if ($user !== []) {
            $event['user'] = $user;
        }

        $release = $input['release'] ?? $scope->release ?? $this->config->release;
        if (is_string($release) && $release !== '') {
            $event['release'] = $release;
        }

        $fingerprint = $input['fingerprint'] ?? $scope->fingerprint;
        if ($fingerprint !== null) {
            $event['fingerprint'] = $fingerprint;
        }

        if ($scope->contexts !== []) {
            $event['contexts'] = $scope->contexts;
        }

        if (is_callable($this->config->beforeSend)) {
            $result = ($this->config->beforeSend)($event);
            if ($result === null) {
                return [];
            }
            $event = $result;
        }

        /** @var array<string,mixed> $redacted */
        $redacted = $this->redactor->redact($event);

        return $redacted;
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,string>
     */
    public static function userPick(array $user): array
    {
        $out = [];
        foreach (self::USER_KEYS as $k) {
            $v = $user[$k] ?? null;
            if (is_string($v) || is_int($v) || is_float($v)) {
                $s = (string) $v;
                if (trim($s) !== '') {
                    $out[$k] = $s;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $kv
     * @return array<string,string>
     */
    public static function stringMap(array $kv): array
    {
        $out = [];
        foreach ($kv as $k => $v) {
            if (is_string($v) || is_int($v) || is_float($v) || is_bool($v)) {
                $out[(string) $k] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
            }
        }

        return $out;
    }
}

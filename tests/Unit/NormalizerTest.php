<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit;

use NewInstance\BugWatch\Config;
use NewInstance\BugWatch\Context\Scope;
use NewInstance\BugWatch\Normalizer;
use NewInstance\BugWatch\Redaction\Redactor;
use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase
{
    private function normalizer(array $opts = []): Normalizer
    {
        $config = Config::fromArray(['projectKey' => 'k:s'] + $opts);

        return new Normalizer($config, new Redactor($config->sensitiveFields));
    }

    public function test_message_event_shape(): void
    {
        $event = $this->normalizer()->build(['message' => 'hello', 'level' => 'warning'], new Scope());

        self::assertMatchesRegularExpression('/^bw_e_/', $event['eventId']);
        self::assertSame(40, $event['level']);
        self::assertSame('hello', $event['message']);
        self::assertIsInt($event['time']);
        self::assertSame(['name' => 'newinstance/bugwatch-php', 'version' => '0.1.0'], $event['sdk']);
        self::assertArrayNotHasKey('environment', $event);
    }

    public function test_exception_event_includes_exception_and_causes(): void
    {
        $e = new \RuntimeException('outer', 0, new \LogicException('inner'));
        $event = $this->normalizer()->build(['exception' => $e], new Scope());

        self::assertSame(\RuntimeException::class, $event['exception']['type']);
        self::assertSame('outer', $event['message']);
        self::assertSame('inner', $event['causes'][0]['value']);
    }

    public function test_input_tags_override_scope_and_user_is_allowlisted(): void
    {
        $scope = new Scope();
        $scope->tags = ['region' => 'eu', 'plan' => 'free'];
        $event = $this->normalizer()->build([
            'message' => 'm',
            'tags' => ['plan' => 'pro'],
            'user' => ['id' => 'u1', 'password' => 'nope', 'email' => 'a@b.com'],
        ], $scope);

        self::assertSame(['region' => 'eu', 'plan' => 'pro'], $event['tags']);
        self::assertSame(['id' => 'u1', 'email' => 'a@b.com'], $event['user']);
    }

    public function test_redaction_runs_over_event(): void
    {
        $event = $this->normalizer()->build(['message' => 'm', 'tags' => ['apikey' => 'sekret']], new Scope());
        self::assertSame('[REDACTED]', $event['tags']['apikey']);
    }

    public function test_before_send_can_drop(): void
    {
        $event = $this->normalizer(['beforeSend' => static fn () => null])->build(['message' => 'm'], new Scope());
        self::assertSame([], $event);
    }

    public function test_before_send_can_mutate_and_redaction_still_runs(): void
    {
        $n = $this->normalizer(['beforeSend' => static function (array $e): array {
            $e['tags']['injected'] = 'yes';
            $e['user'] = ['password' => 'leak'];

            return $e;
        }]);
        $event = $n->build(['message' => 'm'], new Scope());

        self::assertSame('yes', $event['tags']['injected']);
        self::assertSame('[REDACTED]', $event['user']['password']);
    }
}

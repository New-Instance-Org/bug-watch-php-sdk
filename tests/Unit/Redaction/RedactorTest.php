<?php

declare(strict_types=1);

namespace NewInstance\BugWatch\Tests\Unit\Redaction;

use NewInstance\BugWatch\Redaction\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
    public function test_redacts_default_and_extra_keys_case_insensitively(): void
    {
        $r = new Redactor(['custom_secret']);
        $out = $r->redact([
            'Password' => 'p',
            'user' => ['email' => 'a@b.com', 'token' => 't'],
            'custom_secret' => 'x',
            'keep' => 'v',
        ]);

        self::assertSame('[REDACTED]', $out['Password']);
        self::assertSame('a@b.com', $out['user']['email']);
        self::assertSame('[REDACTED]', $out['user']['token']);
        self::assertSame('[REDACTED]', $out['custom_secret']);
        self::assertSame('v', $out['keep']);
    }

    public function test_depth_cap_stops_recursion(): void
    {
        $deep = ['l' => ['l' => ['l' => ['l' => ['l' => ['l' => ['l' => ['l' => ['l' => ['password' => 'p']]]]]]]]]];
        $out = $r = (new Redactor())->redact($deep);
        // Beyond depth 8 the structure is returned untouched (password NOT redacted at depth 9).
        self::assertSame('p', $out['l']['l']['l']['l']['l']['l']['l']['l']['l']['password']);
    }

    public function test_scalars_pass_through(): void
    {
        self::assertSame('hello', (new Redactor())->redact('hello'));
        self::assertSame(42, (new Redactor())->redact(42));
    }
}

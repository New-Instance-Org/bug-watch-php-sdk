<?php

/**
 * PHPStan-only stub. The REAL class ships with symfony/http-foundation (a transitive
 * dependency, present in vendor/ and used at runtime). PHPStan 1.12 cannot reflect the
 * real class in this project on PHP 8.5, so this minimal symbol lets PHPStan resolve the
 * `Symfony\Component\HttpFoundation\Response` return type of BugWatchExceptionHandler::render().
 * It is referenced only via phpstan.neon.dist `scanFiles` and is never autoloaded at runtime.
 */

namespace Symfony\Component\HttpFoundation;

class Response
{
}

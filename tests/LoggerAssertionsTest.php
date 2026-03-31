<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\LoggerAssertionsTrait;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\Support\CodeceptTestCase;

final class LoggerAssertionsTest extends CodeceptTestCase
{
    use LoggerAssertionsTrait;

    #[IgnoreDeprecations]
    public function testDeprecationsAreReported(): void
    {
        $this->client->request('GET', '/deprecated');
        try {
            $this->dontSeeDeprecations();
            self::fail('Expected deprecations to be reported.');
        } catch (AssertionFailedError $error) {
            $this->assertStringContainsString('deprecation', $error->getMessage());
        }
    }

    public function testDontSeeDeprecations(): void
    {
        $this->client->request('GET', '/sample');
        $this->dontSeeDeprecations();
    }
}

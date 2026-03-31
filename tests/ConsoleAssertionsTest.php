<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\ConsoleAssertionsTrait;
use Tests\Support\CodeceptTestCase;

final class ConsoleAssertionsTest extends CodeceptTestCase
{
    use ConsoleAssertionsTrait;

    public function testRunSymfonyConsoleCommand(): void
    {
        $this->assertStringContainsString('No option', $this->runSymfonyConsoleCommand('app:test-command'));
        $this->assertStringContainsString('Option selected', $this->runSymfonyConsoleCommand('app:test-command', ['--opt' => true]));
        $this->assertStringContainsString('Option selected', $this->runSymfonyConsoleCommand('app:test-command', ['-o' => true]));
        $this->assertSame('', $this->runSymfonyConsoleCommand('app:test-command', ['-q']));
    }
}

<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\ConsoleAssertionsTrait;
use Symfony\Component\Console\Tester\ExecutionResult;
use Tests\Support\CodeceptTestCase;

use function class_exists;

final class ConsoleAssertionsTest extends CodeceptTestCase
{
    use ConsoleAssertionsTrait;

    public function testAssertCommandFailed(): void
    {
        $this->requireExecutionResult();

        $result = $this->runCommand('app:test-command', ['--fail' => true]);

        $this->assertCommandFailed($result);
        $this->assertStringContainsString('Something went wrong', $result->getErrorOutput());
        $this->assertStringNotContainsString('Something went wrong', $result->getOutput());
    }

    public function testAssertCommandIsInvalid(): void
    {
        $this->requireExecutionResult();

        $result = $this->runCommand('app:test-command', ['--invalid' => true]);

        $this->assertCommandIsInvalid($result);
    }

    public function testAssertCommandResultEquals(): void
    {
        $this->requireExecutionResult();

        $result = $this->runCommand('app:test-command', ['--fail' => true]);

        $this->assertCommandResultEquals(
            $result,
            expectedStatusCode: 1,
            expectedErrorOutput: 'Something went wrong',
        );
    }

    public function testRunCommand(): void
    {
        $this->requireExecutionResult();

        $result = $this->runCommand('app:test-command');

        $this->assertCommandIsSuccessful($result);
        $this->assertSame(0, $result->statusCode);
        $this->assertStringContainsString('No option', $result->getOutput());
    }

    public function testRunSymfonyConsoleCommand(): void
    {
        $this->assertStringContainsString('No option', $this->runSymfonyConsoleCommand('app:test-command'));
        $this->assertStringContainsString('Option selected', $this->runSymfonyConsoleCommand('app:test-command', ['--opt' => true]));
        $this->assertStringContainsString('Option selected', $this->runSymfonyConsoleCommand('app:test-command', ['-o' => true]));
        $this->assertSame('', $this->runSymfonyConsoleCommand('app:test-command', ['-q']));
    }

    private function requireExecutionResult(): void
    {
        if (!class_exists(ExecutionResult::class)) {
            $this->markTestSkipped('symfony/console 8.1 or higher is required for runCommand().');
        }
    }
}

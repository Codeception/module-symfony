<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\Constraint\CommandFailed;
use Symfony\Component\Console\Tester\Constraint\CommandIsInvalid;
use Symfony\Component\Console\Tester\Constraint\CommandIsSuccessful;
use Symfony\Component\Console\Tester\ExecutionResult;
use Symfony\Component\HttpKernel\KernelInterface;

use function class_exists;
use function is_int;
use function sprintf;

trait ConsoleAssertionsTrait
{
    /**
     * Asserts that a command run with [`runCommand()`](https://codeception.com/docs/modules/Symfony#runCommand)
     * exited with a non-zero (failure) status code.
     *
     * ```php
     * <?php
     * $result = $I->runCommand('app:import-users', ['file' => 'broken.csv']);
     * $I->assertCommandFailed($result);
     * ```
     */
    public function assertCommandFailed(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandFailed(), $message);
    }

    /**
     * Asserts that a command run with [`runCommand()`](https://codeception.com/docs/modules/Symfony#runCommand)
     * exited with the "invalid" status code (`Command::INVALID`, i.e. `2`).
     *
     * ```php
     * <?php
     * $result = $I->runCommand('app:import-users');
     * $I->assertCommandIsInvalid($result);
     * ```
     */
    public function assertCommandIsInvalid(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandIsInvalid(), $message);
    }

    /**
     * Asserts that a command run with [`runCommand()`](https://codeception.com/docs/modules/Symfony#runCommand)
     * exited successfully (status code `0`).
     *
     * ```php
     * <?php
     * $result = $I->runCommand('app:import-users', ['file' => 'users.csv']);
     * $I->assertCommandIsSuccessful($result);
     * ```
     */
    public function assertCommandIsSuccessful(ExecutionResult $result, string $message = ''): void
    {
        $this->assertThat($result->statusCode, new CommandIsSuccessful(), $message);
    }

    /**
     * Asserts on the parts of an {@see ExecutionResult} you pass: any of the
     * status code, stdout, stderr and the combined display. Arguments left `null`
     * are not checked.
     *
     * ```php
     * <?php
     * $result = $I->runCommand('app:import-users', ['file' => 'broken.csv']);
     * $I->assertCommandResultEquals(
     *     $result,
     *     expectedStatusCode: 1,
     *     expectedErrorOutput: "Invalid CSV\n",
     * );
     * ```
     */
    public function assertCommandResultEquals(
        ExecutionResult $result,
        ?int $expectedStatusCode = null,
        ?string $expectedOutput = null,
        ?string $expectedErrorOutput = null,
        ?string $expectedDisplay = null,
        string $message = ''
    ): void {
        $expected = [];
        $actual = [];

        if ($expectedStatusCode !== null) {
            $expected['statusCode'] = $expectedStatusCode;
            $actual['statusCode'] = $result->statusCode;
        }
        if ($expectedOutput !== null) {
            $expected['output'] = $expectedOutput;
            $actual['output'] = $result->getOutput();
        }
        if ($expectedErrorOutput !== null) {
            $expected['errorOutput'] = $expectedErrorOutput;
            $actual['errorOutput'] = $result->getErrorOutput();
        }
        if ($expectedDisplay !== null) {
            $expected['display'] = $expectedDisplay;
            $actual['display'] = $result->getDisplay();
        }

        $this->assertSame($expected, $actual, $message);
    }

    /**
     * Runs a console command and returns its {@see ExecutionResult}, which exposes
     * the exit status code together with stdout, stderr and the combined display as
     * separate streams.
     *
     * Unlike [`runSymfonyConsoleCommand()`](https://codeception.com/docs/modules/Symfony#runSymfonyConsoleCommand),
     * which merges stdout and stderr into a single string, this lets you assert on
     * the error output in isolation. Pair it with [`assertCommandIsSuccessful()`](https://codeception.com/docs/modules/Symfony#assertCommandIsSuccessful),
     * [`assertCommandFailed()`](https://codeception.com/docs/modules/Symfony#assertCommandFailed),
     * [`assertCommandIsInvalid()`](https://codeception.com/docs/modules/Symfony#assertCommandIsInvalid)
     * or [`assertCommandResultEquals()`](https://codeception.com/docs/modules/Symfony#assertCommandResultEquals).
     *
     * Requires `symfony/console` 8.1 or higher; on older versions use `runSymfonyConsoleCommand()`.
     *
     * ```php
     * <?php
     * $result = $I->runCommand('app:import-users', ['file' => 'broken.csv']);
     * $I->assertCommandFailed($result);
     * $I->assertStringContainsString('Invalid CSV', $result->getErrorOutput());
     * ```
     *
     * @param array<string, mixed>             $input             Command arguments and options
     * @param list<string>                     $interactiveInputs Inputs for interactive questions
     * @param OutputInterface::VERBOSITY_*|null $verbosity
     * @param array<\Closure(string): string>  $normalizers
     */
    public function runCommand(
        string $name,
        array $input = [],
        array $interactiveInputs = [],
        ?bool $interactive = null,
        ?bool $decorated = null,
        ?int $verbosity = null,
        array $normalizers = []
    ): ExecutionResult {
        if (!class_exists(ExecutionResult::class)) {
            Assert::fail('runCommand() requires symfony/console 8.1 or higher; use runSymfonyConsoleCommand() on older versions.');
        }

        $command = (new Application($this->kernel))->find($name);

        return (new CommandTester($command))->run($input, $interactiveInputs, $interactive, $decorated, $verbosity, $normalizers);
    }

    /**
     * Run Symfony console command, grab response and return as string.
     * Recommended to use for functional testing.
     *
     * Note: The command execution is isolated to bypass global application events, preventing unintended side effects.
     *
     * ```php
     * <?php
     * $result = $I->runSymfonyConsoleCommand('hello:world', ['arg' => 'argValue', 'opt1' => 'optValue'], ['input']);
     * ```
     *
     * @param string                             $command          The console command to execute.
     * @param array<int|string, int|string|bool> $parameters       Arguments and options passed to the command
     * @param list<string>                       $consoleInputs    Inputs for interactive questions.
     * @param int                                $expectedExitCode Expected exit code.
     * @return string Console output (stdout).
     */
    public function runSymfonyConsoleCommand(
        string $command,
        array $parameters = [],
        array $consoleInputs = [],
        int $expectedExitCode = 0
    ): string {
        $consoleCommand = (new Application($this->kernel))->find($command);
        $commandTester  = new CommandTester($consoleCommand);
        $commandTester->setInputs($consoleInputs);

        $options  = $this->configureOptions($parameters);
        $exitCode = $commandTester->execute(['command' => $command] + $parameters, $options);
        $output   = $commandTester->getDisplay();

        $this->assertSame(
            $expectedExitCode,
            $exitCode,
            sprintf('Command exited with %d instead of expected %d. Output: %s', $exitCode, $expectedExitCode, $output)
        );

        return $output;
    }

    /**
     * @param array<int|string, int|string|bool> $parameters
     * @return array<string, bool|int> Options array supported by CommandTester.
     */
    private function configureOptions(array $parameters): array
    {
        /** @var array<string, bool|int> $options */
        $options = [];

        foreach ($parameters as $key => $value) {
            $option = is_int($key) ? (string) $value : $key;

            match ($option) {
                '--ansi'                 => $options['decorated'] = true,
                '--no-ansi'              => $options['decorated'] = false,
                '--no-interaction', '-n' => $options['interactive'] = false,
                '-q', '--quiet'          => $options['verbosity'] = OutputInterface::VERBOSITY_QUIET,
                '-v', '--verbose=1'      => $options['verbosity'] = OutputInterface::VERBOSITY_VERBOSE,
                '-vv', '--verbose=2'     => $options['verbosity'] = OutputInterface::VERBOSITY_VERY_VERBOSE,
                '-vvv', '--verbose=3'    => $options['verbosity'] = OutputInterface::VERBOSITY_DEBUG,
                '--verbose'              => $options['verbosity'] = match ((int) $value) {
                    3       => OutputInterface::VERBOSITY_DEBUG,
                    2       => OutputInterface::VERBOSITY_VERY_VERBOSE,
                    default => OutputInterface::VERBOSITY_VERBOSE,
                },
                default => null,
            };
        }

        if (($options['verbosity'] ?? null) === OutputInterface::VERBOSITY_QUIET) {
            $options['interactive'] = false;
        }

        return $options;
    }

    protected function grabKernelService(): KernelInterface
    {
        return $this->grabService(KernelInterface::class);
    }
}

<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

use function sprintf;

trait ConsoleAssertionsTrait
{
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

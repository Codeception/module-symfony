<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

use function in_array;
use function sprintf;

trait ConsoleAssertionsTrait
{
    /**
     * Run Symfony console command, grab response and return as string.
     * Recommended to use for integration or functional testing.
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
        $kernel          = $this->grabKernelService();
        $application     = new Application($kernel);
        $consoleCommand  = $application->find($command);
        $commandTester   = new CommandTester($consoleCommand);
        $commandTester->setInputs($consoleInputs);

        $input    = ['command' => $command] + $parameters;
        $options  = $this->configureOptions($parameters);
        $exitCode = $commandTester->execute($input, $options);
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

        if (in_array('--ansi', $parameters, true)) {
            $options['decorated'] = true;
        } elseif (in_array('--no-ansi', $parameters, true)) {
            $options['decorated'] = false;
        }

        if (in_array('--no-interaction', $parameters, true) || in_array('-n', $parameters, true)) {
            $options['interactive'] = false;
        }

        if (in_array('--quiet', $parameters, true) || in_array('-q', $parameters, true)) {
            $options['verbosity']   = OutputInterface::VERBOSITY_QUIET;
            $options['interactive'] = false;
        }

        if (in_array('-vvv', $parameters, true) 
            || in_array('--verbose=3', $parameters, true) 
            || (isset($parameters['--verbose']) && $parameters['--verbose'] === 3)
        ) {
            $options['verbosity'] = OutputInterface::VERBOSITY_DEBUG;
        } elseif (in_array('-vv', $parameters, true) 
            || in_array('--verbose=2', $parameters, true) 
            || (isset($parameters['--verbose']) && $parameters['--verbose'] === 2)
        ) {
            $options['verbosity'] = OutputInterface::VERBOSITY_VERY_VERBOSE;
        } elseif (in_array('-v', $parameters, true) 
            || in_array('--verbose=1', $parameters, true) 
            || in_array('--verbose', $parameters, true) 
            || (isset($parameters['--verbose']) && $parameters['--verbose'] === 1)
        ) {
            $options['verbosity'] = OutputInterface::VERBOSITY_VERBOSE;
        }

        return $options;
    }

    protected function grabKernelService(): KernelInterface
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->grabService(KernelInterface::class);
        return $kernel;
    }
}

<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

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
     * @param string $command          The console command to execute
     * @param array  $parameters       Parameters (arguments and options) to pass to the command
     * @param array  $consoleInputs    Console inputs (e.g. used for interactive questions)
     * @param int    $expectedExitCode The expected exit code of the command
     * @return string Returns the console output of the command
     */
    public function runSymfonyConsoleCommand(string $command, array $parameters = [], array $consoleInputs = [], int $expectedExitCode = 0): string
    {
        $kernel = $this->grabKernelService();
        $application = new Application($kernel);
        $consoleCommand = $application->find($command);
        $commandTester = new CommandTester($consoleCommand);
        $commandTester->setInputs($consoleInputs);

        $parameters = ['command' => $command] + $parameters;
        $exitCode = $commandTester->execute($parameters);
        $output = $commandTester->getDisplay();

        $this->assertSame(
            $expectedExitCode,
            $exitCode,
            'Command did not exit with code ' . $expectedExitCode
            . ' but with ' . $exitCode . ': ' . $output
        );

        return $output;
    }

    protected function grabKernelService(): KernelInterface
    {
        return $this->grabService('kernel');
    }
}
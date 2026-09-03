<?php

declare(strict_types=1);

namespace Tests\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:test-command', 'A test command.')]
final class TestCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('opt', 'o', InputOption::VALUE_NONE, 'Option');
        $this->addOption('fail', null, InputOption::VALUE_NONE, 'Exit with a failure status and write to stderr');
        $this->addOption('invalid', null, InputOption::VALUE_NONE, 'Exit with the invalid status code');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('fail')) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->write('Something went wrong');

            return Command::FAILURE;
        }

        if ($input->getOption('invalid')) {
            return Command::INVALID;
        }

        if ($input->getOption('opt')) {
            $io->text('Option selected');
        } else {
            $io->text('No option');
        }

        return Command::SUCCESS;
    }
}

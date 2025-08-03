<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use function sprintf;

trait LoggerAssertionsTrait
{
    /**
     * Asserts that there are no deprecation messages in Symfony's log.
     *
     * ```php
     * <?php
     * $I->amOnPage('/home');
     * $I->dontSeeDeprecations();
     * ```
     *
     * @param string $message Optional custom failure message.
     */
    public function dontSeeDeprecations(string $message = ''): void
    {
        $logs = $this->grabLoggerCollector(__FUNCTION__)->getProcessedLogs();
        $foundDeprecations = [];

        /** @var array<string, mixed> $log */
        foreach ($logs as $log) {
            if (!isset($log['type']) || $log['type'] !== 'deprecation') {
                continue;
            }
            $msg = $log['message'];
            if ($msg instanceof Data) {
                $msg = $msg->getValue(true);
            }
            if (!is_string($msg) && !is_scalar($msg)) {
                $msg = json_encode($msg, JSON_THROW_ON_ERROR);
            }
            $foundDeprecations[] = (string) $msg;
        }
        $count = count($foundDeprecations);
        $errorMessage = $message ?: sprintf(
            "Found %d deprecation message%s in the log:\n%s",
            $count,
            $count !== 1 ? 's' : '',
            implode("\n", array_map(static fn(string $m): string => "  - $m", $foundDeprecations)),
        );
        $this->assertEmpty($foundDeprecations, $errorMessage);
    }

    protected function grabLoggerCollector(string $function): LoggerDataCollector
    {
        return $this->grabCollector(DataCollectorName::LOGGER, $function);
    }
}

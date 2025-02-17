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
        $loggerCollector = $this->grabLoggerCollector(__FUNCTION__);
        $logs = $loggerCollector->getProcessedLogs();

        $foundDeprecations = [];

        foreach ($logs as $log) {
            if (isset($log['type']) && $log['type'] === 'deprecation') {
                $msg = $log['message'];
                if ($msg instanceof Data) {
                    $msg = $msg->getValue(true);
                }
                if (!is_string($msg)) {
                    $msg = (string)$msg;
                }
                $foundDeprecations[] = $msg;
            }
        }

        $errorMessage = $message ?: sprintf(
            "Found %d deprecation message%s in the log:\n%s",
            count($foundDeprecations),
            count($foundDeprecations) > 1 ? 's' : '',
            implode("\n", array_map(static function ($msg) {
                return "  - " . $msg;
            }, $foundDeprecations))
        );

        $this->assertEmpty($foundDeprecations, $errorMessage);
    }

    protected function grabLoggerCollector(string $function): LoggerDataCollector
    {
        return $this->grabCollector('logger', $function);
    }
}

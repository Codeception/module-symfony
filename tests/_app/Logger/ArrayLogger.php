<?php

declare(strict_types=1);

namespace Tests\App\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

final class ArrayLogger extends AbstractLogger implements DebugLoggerInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $logs = [];

    public function clear(): void
    {
        $this->logs = [];
    }

    public function countErrors(?Request $request = null): int
    {
        return count(array_filter(
            $this->logs,
            static fn(array $log): bool => $log['priority'] >= 400,
        ));
    }

    public function getLogs(?Request $request = null): array
    {
        return $this->logs;
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $priorityName = strtoupper((string) $level);

        $priority = match ((string) $level) {
            LogLevel::DEBUG => 100,
            LogLevel::INFO => 200,
            LogLevel::NOTICE => 250,
            LogLevel::WARNING => 300,
            LogLevel::ERROR => 400,
            LogLevel::CRITICAL => 500,
            LogLevel::ALERT => 550,
            LogLevel::EMERGENCY => 600,
            default => 200,
        };

        $this->logs[] = [
            'message' => (string) $message,
            'context' => $context,
            'priority' => $priority,
            'priorityName' => $priorityName,
            'channel' => 'app',
            'timestamp' => time(),
            'timestamp_rfc3339' => date(DATE_RFC3339),
        ];
    }
}

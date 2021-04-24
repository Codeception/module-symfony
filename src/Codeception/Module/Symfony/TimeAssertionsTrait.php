<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use function number_format;
use function sprintf;

trait TimeAssertionsTrait
{
    /**
     * Asserts that the time a request lasted is less than expected (`$expectedTime`).
     *
     * @param float $expectedTime The expected time in milliseconds
     */
    public function seeRequestElapsedTimeLessThan(float $expectedTime): void
    {
        $timeCollector = $this->grabTimeCollector(__FUNCTION__);

        $realTime = $timeCollector->getDuration();

        $this->assertLessThan(
            $expectedTime,
            $realTime,
            sprintf(
                'The request was expected to last less than %s ms, but it actually lasted %s ms.',
                number_format($expectedTime, 2),
                number_format($realTime, 2)
            )
        );
    }

    protected function grabTimeCollector(string $function): TimeDataCollector
    {
        return $this->grabCollector('time', $function);
    }
}

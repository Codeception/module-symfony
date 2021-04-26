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
     * If the page performed a HTTP redirect, only the time of the last request will be taken into account.
     * You can modify this behavior using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first.
     *
     * Also, note that using code coverage can significantly increase the time it takes to resolve a request,
     * which could lead to unreliable results when used together.
     *
     * @param float $expectedMilliseconds The expected time in milliseconds
     */
    public function seeRequestTimeIsLessThan(float $expectedMilliseconds): void
    {
        $timeCollector = $this->grabTimeCollector(__FUNCTION__);

        $actualMilliseconds = $timeCollector->getDuration();

        $this->assertLessThan(
            $expectedMilliseconds,
            $actualMilliseconds,
            sprintf(
                'The request was expected to last less than %s ms, but it actually lasted %s ms.',
                number_format($expectedMilliseconds, 2),
                number_format($actualMilliseconds, 2)
            )
        );
    }

    protected function grabTimeCollector(string $function): TimeDataCollector
    {
        return $this->grabCollector('time', $function);
    }
}

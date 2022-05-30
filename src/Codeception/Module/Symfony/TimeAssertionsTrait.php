<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use function round;
use function sprintf;

trait TimeAssertionsTrait
{
    /**
     * Asserts that the time a request lasted is less than expected.
     *
     * If the page performed a HTTP redirect, only the time of the last request will be taken into account.
     * You can modify this behavior using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first.
     *
     * Also, note that using code coverage can significantly increase the time it takes to resolve a request,
     * which could lead to unreliable results when used together.
     *
     * It is recommended to set [`rebootable_client`](https://codeception.com/docs/modules/Symfony#Config) to `true` (=default),
     * cause otherwise this assertion gives false results if you access multiple pages in a row, or if your app performs a redirect.
     *
     * @param int|float $expectedMilliseconds The expected time in milliseconds
     */
    public function seeRequestTimeIsLessThan(int|float $expectedMilliseconds): void
    {
        $expectedMilliseconds = round($expectedMilliseconds, 2);

        $timeCollector = $this->grabTimeCollector(__FUNCTION__);

        $actualMilliseconds = round($timeCollector->getDuration(), 2);

        $this->assertLessThan(
            $expectedMilliseconds,
            $actualMilliseconds,
            sprintf(
                'The request was expected to last less than %d ms, but it actually lasted %d ms.',
                $expectedMilliseconds,
                $actualMilliseconds
            )
        );
    }

    protected function grabTimeCollector(string $function): TimeDataCollector
    {
        return $this->grabCollector('time', $function);
    }
}

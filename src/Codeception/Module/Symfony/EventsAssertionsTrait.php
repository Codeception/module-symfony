<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use function get_class;
use function is_array;
use function is_object;
use function strpos;

trait EventsAssertionsTrait
{
    /**
     * Make sure events did not fire during the test.
     *
     * ``` php
     * <?php
     * $I->dontSeeEventTriggered('App\MyEvent');
     * $I->dontSeeEventTriggered(new App\Events\MyEvent());
     * $I->dontSeeEventTriggered(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param string|object|string[] $expected
     */
    public function dontSeeEventTriggered($expected): void
    {
        /** @var EventDataCollector $eventCollector */
        $eventCollector = $this->grabCollector('events', __FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getNotCalledListeners();

        $actual = $data->getValue(true);
        $expected = is_array($expected) ? $expected : [$expected];

        foreach ($expected as $expectedEvent) {
            $notTriggered = false;
            $expectedEvent = is_object($expectedEvent) ? get_class($expectedEvent) : $expectedEvent;

            foreach ($actual as $actualEvent) {
                if (strpos($actualEvent['pretty'], $expectedEvent) === 0) {
                    $notTriggered = true;
                }
            }
            $this->assertTrue($notTriggered, "The '$expectedEvent' event triggered");
        }
    }

    /**
     * Make sure events fired during the test.
     *
     * ``` php
     * <?php
     * $I->seeEventTriggered('App\MyEvent');
     * $I->seeEventTriggered(new App\Events\MyEvent());
     * $I->seeEventTriggered(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param string|object|string[] $expected
     */
    public function seeEventTriggered($expected): void
    {
        /** @var EventDataCollector $eventCollector */
        $eventCollector = $this->grabCollector('events', __FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();

        if ($data->count() === 0) {
            $this->fail('No event was triggered');
        }

        $actual = $data->getValue(true);
        $expected = is_array($expected) ? $expected : [$expected];

        foreach ($expected as $expectedEvent) {
            $triggered = false;
            $expectedEvent = is_object($expectedEvent) ? get_class($expectedEvent) : $expectedEvent;

            foreach ($actual as $actualEvent) {
                if (strpos($actualEvent['pretty'], $expectedEvent) === 0) {
                    $triggered = true;
                }
            }
            $this->assertTrue($triggered, "The '$expectedEvent' event did not trigger");
        }
    }
}
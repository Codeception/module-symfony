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
     * Verifies that one or more event listeners were not called during the test.
     *
     * ```php
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
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventNotTriggered($data, $expected);
    }

    /**
     * Verifies that one or more event listeners were called during the test.
     *
     * ```php
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
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventTriggered($data, $expected);
    }

    protected function assertEventNotTriggered(Data $data, array $expected): void
    {
        if ($data->count() === 0) {
            $this->fail('No event was triggered');
        }

        $actual = $data->getValue(true);

        foreach ($expected as $expectedEvent) {
            $expectedEvent = is_object($expectedEvent) ? get_class($expectedEvent) : $expectedEvent;
            $this->assertFalse(
                $this->eventWasTriggered($actual, (string) $expectedEvent),
                "The '{$expectedEvent}' event triggered"
            );
        }
    }

    protected function assertEventTriggered(Data $data, array $expected): void
    {
        $actual = $data->getValue(true);

        foreach ($expected as $expectedEvent) {
            $expectedEvent = is_object($expectedEvent) ? get_class($expectedEvent) : $expectedEvent;
            $this->assertTrue(
                $this->eventWasTriggered($actual, (string) $expectedEvent),
                "The '{$expectedEvent}' event did not trigger"
            );
        }
    }

    protected function eventWasTriggered(array $actual, string $expectedEvent): bool
    {
        $triggered = false;

        foreach ($actual as $actualEvent) {
            if (strpos($actualEvent['pretty'], $expectedEvent) === 0) {
                $triggered = true;
            }
        }
        return $triggered;
    }

    protected function grabEventCollector(string $function): EventDataCollector
    {
        return $this->grabCollector('events', $function);
    }
}
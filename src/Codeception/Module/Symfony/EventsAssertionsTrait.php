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
     * Verifies that there were no orphan events during the test.
     *
     * An orphan event is an event that was triggered by manually executing the
     * [`dispatch()`](https://symfony.com/doc/current/components/event_dispatcher.html#dispatch-the-event) method
     * of the EventDispatcher but was not handled by any listener after it was dispatched.
     *
     * ```php
     * <?php
     * $I->dontSeeOrphanEvent();
     * $I->dontSeeOrphanEvent('App\MyEvent');
     * $I->dontSeeOrphanEvent(new App\Events\MyEvent());
     * $I->dontSeeOrphanEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param object|string|string[] $expected
     */
    public function dontSeeOrphanEvent(array|object|string $expected = null): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getOrphanedEvents();
        $expected = is_array($expected) ? $expected : [$expected];

        if ($expected === null) {
            $this->assertSame(0, $data->count());
        } else {
            $this->assertEventNotTriggered($data, $expected);
        }
    }

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
     * @param object|string|string[] $expected
     */
    public function dontSeeEventTriggered(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventNotTriggered($data, $expected);
    }

    /**
     * Verifies that one or more orphan events were dispatched during the test.
     *
     * An orphan event is an event that was triggered by manually executing the
     * [`dispatch()`](https://symfony.com/doc/current/components/event_dispatcher.html#dispatch-the-event) method
     * of the EventDispatcher but was not handled by any listener after it was dispatched.
     *
     * ```php
     * <?php
     * $I->seeOrphanEvent('App\MyEvent');
     * $I->seeOrphanEvent(new App\Events\MyEvent());
     * $I->seeOrphanEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param object|string|string[] $expected
     */
    public function seeOrphanEvent(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getOrphanedEvents();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventTriggered($data, $expected);
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
     * @param object|string|string[] $expected
     */
    public function seeEventTriggered(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventTriggered($data, $expected);
    }

    protected function assertEventNotTriggered(Data $data, array $expected): void
    {
        $actual = $data->getValue(true);

        foreach ($expected as $expectedEvent) {
            $expectedEvent = is_object($expectedEvent) ? $expectedEvent::class : $expectedEvent;
            $this->assertFalse(
                $this->eventWasTriggered($actual, (string)$expectedEvent),
                "The '{$expectedEvent}' event triggered"
            );
        }
    }

    protected function assertEventTriggered(Data $data, array $expected): void
    {
        if ($data->count() === 0) {
            $this->fail('No event was triggered');
        }

        $actual = $data->getValue(true);

        foreach ($expected as $expectedEvent) {
            $expectedEvent = is_object($expectedEvent) ? $expectedEvent::class : $expectedEvent;
            $this->assertTrue(
                $this->eventWasTriggered($actual, (string)$expectedEvent),
                "The '{$expectedEvent}' event did not trigger"
            );
        }
    }

    protected function eventWasTriggered(array $actual, string $expectedEvent): bool
    {
        $triggered = false;

        foreach ($actual as $actualEvent) {
            if (is_array($actualEvent)) { // Called Listeners
                if (str_starts_with($actualEvent['pretty'], $expectedEvent)) {
                    $triggered = true;
                }
            } else { // Orphan Events
                if ($actualEvent === $expectedEvent) {
                    $triggered = true;
                }
            }
        }
        return $triggered;
    }

    protected function grabEventCollector(string $function): EventDataCollector
    {
        return $this->grabCollector('events', $function);
    }
}
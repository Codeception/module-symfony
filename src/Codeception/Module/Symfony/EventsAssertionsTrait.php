<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

use function is_array;
use function is_object;

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

        $data = $eventCollector->getOrphanedEvents();
        $expected = is_array($expected) ? $expected : [$expected];

        if ($expected === null) {
            $this->assertSame(0, $data->count());
        } else {
            $this->assertEventTriggered($data, $expected, true);
        }
    }

    /**
     * Verifies that there were no events during the test.
     * Both regular and orphan events are checked.
     *
     * ```php
     *  <?php
     *  $I->dontSeeEvent();
     *  $I->dontSeeEvent('App\MyEvent');
     *  $I->dontSeeEvent(new App\Events\MyEvent());
     *  $I->dontSeeEvent(['App\MyEvent', 'App\MyOtherEvent']);
     *  ```
     *
     * @param array|object|string|null $expected
     */
    public function dontSeeEvent(array|object|string $expected = null): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        $data = [
            $eventCollector->getOrphanedEvents(),
            $eventCollector->getCalledListeners(),
        ];
        $expected = is_array($expected) ? $expected : [$expected];

        if ($expected === null) {
            foreach ($data as $dataItem) {
                $this->assertSame(0, $dataItem->count());
            }
        } else {
            $this->assertEventTriggered($data, $expected, true);
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
     * @deprecated Use `dontSeeEventListenerIsCalled` instead.
     */
    public function dontSeeEventTriggered(array|object|string $expected): void
    {
        trigger_error(
            'dontSeeEventTriggered is deprecated, please use dontSeeEventListenerIsCalled instead',
            E_USER_DEPRECATED
        );
        $this->dontSeeEventListenerIsCalled($expected);
    }

    /**
     * Verifies that one or more event listeners were not called during the test.
     *
     * ```php
     * <?php
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener');
     * $I->dontSeeEventListenerIsCalled(new App\Events\MyEventListener());
     * $I->dontSeeEventListenerIsCalled(['App\MyEventListener', 'App\MyOtherEventListener']);
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener', 'my.event);
     * $I->dontSeeEventListenerIsCalled(new App\Events\MyEventListener(), new MyEvent());
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener', ['my.event', 'my.other.event']);
     * ```
     *
     * @param object|string|string[] $expected
     */
    public function dontSeeEventListenerIsCalled(
        array|object|string $expected,
        array|object|string $withEvents = []
    ): void {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];
        $withEvents = is_array($withEvents) ? $withEvents : [$withEvents];

        if (!empty($withEvents) && count($expected) > 1) {
            $this->fail('You cannot check for events when using multiple listeners. Make multiple assertions instead.');
        }

        $this->assertListenerCalled($data, $expected, $withEvents, true);
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

        $data = $eventCollector->getOrphanedEvents();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventTriggered($data, $expected);
    }

    /**
     * Verifies that one or more events were dispatched during the test.
     * Both regular and orphan events are checked.
     *
     * If you need to verify that expected event is not orphan,
     * add `dontSeeOrphanEvent` call.
     *
     * ```php
     *  <?php
     *  $I->seeEvent('App\MyEvent');
     *  $I->seeEvent(new App\Events\MyEvent());
     *  $I->seeEvent(['App\MyEvent', 'App\MyOtherEvent']);
     *  ```
     *
     * @param array|object|string $expected
     */
    public function seeEvent(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        $data = [
            $eventCollector->getOrphanedEvents(),
            $eventCollector->getCalledListeners(),
        ];
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
     * @deprecated Use `seeEventListenerIsCalled` instead.
     */
    public function seeEventTriggered(array|object|string $expected): void
    {
        trigger_error(
            'seeEventTriggered is deprecated, please use seeEventListenerIsCalled instead',
            E_USER_DEPRECATED
        );
        $this->seeEventListenerIsCalled($expected);
    }

    /**
     * Verifies that one or more event listeners were called during the test.
     *
     * ```php
     * <?php
     * $I->seeEventListenerIsCalled('App\MyEventListener');
     * $I->seeEventListenerIsCalled(new App\Events\MyEventListener());
     * $I->seeEventListenerIsCalled(['App\MyEventListener', 'App\MyOtherEventListener']);
     * $I->seeEventListenerIsCalled('App\MyEventListener', 'my.event);
     * $I->seeEventListenerIsCalled(new App\Events\MyEventListener(), new MyEvent());
     * $I->seeEventListenerIsCalled('App\MyEventListener', ['my.event', 'my.other.event']);
     * ```
     *
     * @param object|string|string[] $expected
     */
    public function seeEventListenerIsCalled(
        array|object|string $expected,
        array|object|string $withEvents = []
    ): void {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];
        $withEvents = is_array($withEvents) ? $withEvents : [$withEvents];

        if (!empty($withEvents) && count($expected) > 1) {
            $this->fail('You cannot check for events when using multiple listeners. Make multiple assertions instead.');
        }

        $this->assertListenerCalled($data, $expected, $withEvents);
    }

    protected function assertEventTriggered(
        array|Data $data,
        array $expected,
        bool $invertAssertion = false
    ): void {
        $assertTrue = !$invertAssertion;
        $data = is_array($data) ? $data : [$data];
        $totalEvents = array_sum(array_map('count', $data));

        if ($assertTrue && $totalEvents === 0) {
            $this->fail('No event was triggered');
        }

        $actualEventsCollection = array_map(static fn (Data $data) => $data->getValue(true), $data);

        foreach ($expected as $expectedEvent) {
            $expectedEvent = is_object($expectedEvent) ? $expectedEvent::class : $expectedEvent;
            $message = $assertTrue
                ? "The '{$expectedEvent}' event did not trigger"
                : "The '{$expectedEvent}' event triggered";

            $eventTriggered = false;

            foreach ($actualEventsCollection as $actualEvents) {
                $eventTriggered = $eventTriggered || $this->eventWasTriggered($actualEvents, (string)$expectedEvent);
            }

            if ($assertTrue) {
                $this->assertTrue($eventTriggered, $message);
            } else {
                $this->assertFalse($eventTriggered, $message);
            }
        }
    }

    protected function assertListenerCalled(
        Data $data,
        array $expectedListeners,
        array $withEvents,
        bool $invertAssertion = false
    ): void {
        $assertTrue = !$invertAssertion;

        if ($assertTrue && $data->count() === 0) {
            $this->fail('No event listener was called');
        }

        $actual = $data->getValue(true);
        $expectedEvents = empty($withEvents) ? [null] : $withEvents;

        foreach ($expectedListeners as $expectedListener) {
            $expectedListener = is_object($expectedListener) ? $expectedListener::class : $expectedListener;

            foreach ($expectedEvents as $expectedEvent) {
                $message = "The '{$expectedListener}' listener was called"
                    . ($expectedEvent ? " for the '{$expectedEvent}' event" : '');

                $condition = $this->listenerWasCalled($actual, $expectedListener, $expectedEvent);

                if ($assertTrue) {
                    $this->assertTrue($condition, $message);
                } else {
                    $this->assertFalse($condition, $message);
                }
            }
        }
    }

    protected function eventWasTriggered(array $actual, string $expectedEvent): bool
    {
        $triggered = false;

        foreach ($actual as $actualEvent) {
            if (is_array($actualEvent)) { // Called Listeners
                if ($actualEvent['event'] === $expectedEvent) {
                    $triggered = true;
                }
            } elseif ($actualEvent === $expectedEvent) { // Orphan Events
                $triggered = true;
            }
        }

        return $triggered;
    }

    protected function listenerWasCalled(array $actual, string $expectedListener, string|null $expectedEvent): bool
    {
        $called = false;

        foreach ($actual as $actualEvent) {
            // Called Listeners
            if (is_array($actualEvent) && str_starts_with($actualEvent['pretty'], $expectedListener)) {
                if ($expectedEvent === null) {
                    $called = true;
                } elseif ($actualEvent['event'] === $expectedEvent) {
                    $called = true;
                }
            }
        }

        return $called;
    }

    protected function grabEventCollector(string $function): EventDataCollector
    {
        return $this->grabCollector('events', $function);
    }
}

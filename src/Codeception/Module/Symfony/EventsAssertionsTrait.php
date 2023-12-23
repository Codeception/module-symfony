<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use function is_array;
use function is_object;

trait EventsAssertionsTrait
{
    /**
     * Verifies that there were no events during the test.
     * Both regular and orphan events are checked.
     *
     * ```php
     *  <?php
     *  $I->dontSeeEvent();
     *  $I->dontSeeEvent('App\MyEvent');
     *  $I->dontSeeEvent(['App\MyEvent', 'App\MyOtherEvent']);
     *  ```
     *
     * @param string|string[]|null $expected
     */
    public function dontSeeEvent(array|string $expected = null): void
    {
        $actualEvents = array_merge(array_column($this->getCalledListeners(), 'event'));
        $actual = [$this->getOrphanedEvents(), $actualEvents];
        $this->assertEventTriggered(false, $expected, $actual);
    }

    /**
     * Verifies that one or more event listeners were not called during the test.
     *
     * ```php
     * <?php
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener');
     * $I->dontSeeEventListenerIsCalled(['App\MyEventListener', 'App\MyOtherEventListener']);
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener', 'my.event);
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener', ['my.event', 'my.other.event']);
     * ```
     *
     * @param class-string|class-string[] $expected
     * @param string|string[] $events
     */
    public function dontSeeEventListenerIsCalled(array|object|string $expected, array|string $events = []): void
    {
        $this->assertListenerCalled(false, $expected, $events);
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
     * $I->dontSeeOrphanEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param string|string[] $expected
     */
    public function dontSeeOrphanEvent(array|string $expected = null): void
    {
        $actual = [$this->getOrphanedEvents()];
        $this->assertEventTriggered(false, $expected, $actual);
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
     *  $I->seeEvent(['App\MyEvent', 'App\MyOtherEvent']);
     *  ```
     *
     * @param string|string[] $expected
     */
    public function seeEvent(array|string $expected): void
    {
        $actualEvents = array_merge(array_column($this->getCalledListeners(), 'event'));
        $actual = [$this->getOrphanedEvents(), $actualEvents];
        $this->assertEventTriggered(true, $expected, $actual);
    }

    /**
     * Verifies that one or more event listeners were called during the test.
     *
     * ```php
     * <?php
     * $I->seeEventListenerIsCalled('App\MyEventListener');
     * $I->seeEventListenerIsCalled(['App\MyEventListener', 'App\MyOtherEventListener']);
     * $I->seeEventListenerIsCalled('App\MyEventListener', 'my.event);
     * $I->seeEventListenerIsCalled('App\MyEventListener', ['my.event', 'my.other.event']);
     * ```
     *
     * @param class-string|class-string[] $expected
     * @param string|string[] $events
     */
    public function seeEventListenerIsCalled(array|object|string $expected, array|string $events = []): void
    {
        $this->assertListenerCalled(true, $expected, $events);
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
     * Verifies that one or more orphan events were dispatched during the test.
     *
     * An orphan event is an event that was triggered by manually executing the
     * [`dispatch()`](https://symfony.com/doc/current/components/event_dispatcher.html#dispatch-the-event) method
     * of the EventDispatcher but was not handled by any listener after it was dispatched.
     *
     * ```php
     * <?php
     * $I->seeOrphanEvent('App\MyEvent');
     * $I->seeOrphanEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param string|string[] $expected
     */
    public function seeOrphanEvent(array|string $expected): void
    {
        $actual = [$this->getOrphanedEvents()];
        $this->assertEventTriggered(true, $expected, $actual);
    }

    protected function getCalledListeners(): array
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);
        $calledListeners = $eventCollector->getCalledListeners($this->getDefaultDispatcher());
        return [...$calledListeners->getValue(true)];
    }

    protected function getOrphanedEvents(): array
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);
        $orphanedEvents = $eventCollector->getOrphanedEvents($this->getDefaultDispatcher());
        return [...$orphanedEvents->getValue(true)];
    }

    protected function assertEventTriggered(bool $assertTrue, array|object|string|null $expected, array $actual): void
    {
        $actualEvents = array_merge(...$actual);

        if ($assertTrue) $this->assertNotEmpty($actualEvents, 'No event was triggered');
        if ($expected === null) {
            $this->assertEmpty($actualEvents);
            return;
        }

        $expected = is_object($expected) ? $expected::class : $expected;
        foreach ((array)$expected as $expectedEvent) {
            $expectedEvent = is_object($expectedEvent) ? $expectedEvent::class : $expectedEvent;
            $eventTriggered = in_array($expectedEvent, $actualEvents);

            $message = $assertTrue
                ? "The '{$expectedEvent}' event did not trigger"
                : "The '{$expectedEvent}' event triggered";
            $this->assertSame($assertTrue, $eventTriggered, $message);
        }
    }

    protected function assertListenerCalled(bool $assertTrue, array|object|string $expectedListeners, array|object|string $expectedEvents): void
    {
        $expectedListeners = is_array($expectedListeners) ? $expectedListeners : [$expectedListeners];
        $expectedEvents = is_array($expectedEvents) ? $expectedEvents : [$expectedEvents];

        if (empty($expectedEvents)) {
            $expectedEvents = [null];
        } elseif (count($expectedListeners) > 1) {
            $this->fail('You cannot check for events when using multiple listeners. Make multiple assertions instead.');
        }

        $actualEvents = $this->getCalledListeners();
        if ($assertTrue && empty($actualEvents)) {
            $this->fail('No event listener was called');
        }

        foreach ($expectedListeners as $expectedListener) {
            $expectedListener = is_object($expectedListener) ? $expectedListener::class : $expectedListener;

            foreach ($expectedEvents as $expectedEvent) {
                $listenerCalled = $this->listenerWasCalled($expectedListener, $expectedEvent, $actualEvents);
                $message = "The '{$expectedListener}' listener was called"
                    . ($expectedEvent ? " for the '{$expectedEvent}' event" : '');
                $this->assertSame($assertTrue, $listenerCalled, $message);
            }
        }
    }

    private function listenerWasCalled(string $expectedListener, ?string $expectedEvent, array $actualEvents): bool
    {
        foreach ($actualEvents as $actualEvent) {
            if (
                isset($actualEvent['pretty'], $actualEvent['event'])
                && str_starts_with($actualEvent['pretty'], $expectedListener)
                && ($expectedEvent === null || $actualEvent['event'] === $expectedEvent)
            ) {
                return true;
            }
        }
        return false;
    }

    protected function getDefaultDispatcher(): string
    {
        return 'event_dispatcher';
    }

    protected function grabEventCollector(string $function): EventDataCollector
    {
        return $this->grabCollector('events', $function);
    }
}

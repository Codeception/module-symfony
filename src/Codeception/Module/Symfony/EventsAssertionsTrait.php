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
     * Verifies that one or more events were not dispatched during the test.
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
    public function dontSeeEvent(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventNotTriggered($data, $expected);
    }

    /**
     * Verifies that one or more event listeners were not called during the test.
     *
     * ```php
     * <?php
     * $I->dontSeeEventTriggered('App\MyEventSubscriber');
     * $I->dontSeeEventTriggered(new App\Events\MyEventSubscriber());
     * $I->dontSeeEventTriggered(['App\MyEventSubscriber', 'App\MyOtherEventSubscriber']);
     * ```
     *
     * @param object|string|string[] $expected
     * @deprecated Use `dontSeeEventListenerCalled` instead.
     */
    public function dontSeeEventTriggered(array|object|string $expected): void
    {
        $this->dontSeeEventListenerCalled($expected);
    }

    /**
     * Verifies that one or more event listeners were not called during the test.
     *
     * ```php
     * <?php
     * $I->dontSeeEventListenerCalled('App\MyEventSubscriber');
     * $I->dontSeeEventListenerCalled(new App\Events\MyEventSubscriber());
     * $I->dontSeeEventListenerCalled(['App\MyEventSubscriber', 'App\MyOtherEventSubscriber']);
     * ```
     *
     * @param object|string|string[] $expected
     */
    public function dontSeeEventListenerCalled(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertListenerNotCalled($data, $expected);
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
     * Verifies that one or more events were dispatched during the test.
     *
     * ```php
     * <?php
     * $I->seeEvent('App\MyEvent');
     * $I->seeEvent(new App\Events\MyEvent());
     * $I->seeEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param object|string|string[] $expected
     */
    public function seeEvent(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertEventTriggered($data, $expected);
    }

    /**
     * Verifies that one or more event listeners were called during the test.
     *
     * ```php
     * <?php
     * $I->seeEventTriggered('App\MyEventSubscriber');
     * $I->seeEventTriggered(new App\Events\MyEventSubscriber());
     * $I->seeEventTriggered(['App\MyEventSubscriber', 'App\MyOtherEventSubscriber']);
     * ```
     *
     * @param object|string|string[] $expected
     * @deprecated Use `seeEventListenerCalled` instead.
     */
    public function seeEventTriggered(array|object|string $expected): void
    {
        $this->seeEventListenerCalled($expected);
    }

    /**
     * Verifies that one or more event listeners were called during the test.
     *
     * ```php
     * <?php
     * $I->seeEventListenerCalled('App\MyEventSubscriber');
     * $I->seeEventListenerCalled(new App\Events\MyEventSubscriber());
     * $I->seeEventListenerCalled(['App\MyEventSubscriber', 'App\MyOtherEventSubscriber']);
     * ```
     *
     * @param object|string|string[] $expected
     */
    public function seeEventListenerCalled(array|object|string $expected): void
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);

        /** @var Data $data */
        $data = $eventCollector->getCalledListeners();
        $expected = is_array($expected) ? $expected : [$expected];

        $this->assertListenerCalled($data, $expected);
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

    protected function assertListenerNotCalled(Data $data, array $expected): void
    {
        $actual = $data->getValue(true);

        foreach ($expected as $expectedListener) {
            $expectedListener = is_object($expectedListener) ? $expectedListener::class : $expectedListener;
            $this->assertFalse(
                $this->listenerWasCalled($actual, (string)$expectedListener),
                "The '{$expectedListener}' listener was called"
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

    protected function assertListenerCalled(Data $data, array $expected): void
    {
        if ($data->count() === 0) {
            $this->fail('No listener was called');
        }

        $actual = $data->getValue(true);

        foreach ($expected as $expectedListener) {
            $expectedListener = is_object($expectedListener) ? $expectedListener::class : $expectedListener;
            $this->assertTrue(
                $this->listenerWasCalled($actual, (string) $expectedListener),
                "The '{$expectedListener}' listener was not called"
            );
        }
    }

    protected function eventWasTriggered(array $actual, string $expectedEvent): bool
    {
        foreach ($actual as $actualEvent) {
            if (is_array($actualEvent)) { // Called Listeners
                if ($actualEvent['event'] === $expectedEvent) {
                    return true;
                }
            } else { // Orphan Events
                if ($actualEvent === $expectedEvent) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function listenerWasCalled(array $actual, string $expectedListener): bool
    {
        foreach ($actual as $actualEvent) {
            if (str_starts_with($actualEvent['pretty'], $expectedListener)) {
                return true;
            }
        }

        return false;
    }

    protected function grabEventCollector(string $function): EventDataCollector
    {
        return $this->grabCollector('events', $function);
    }
}

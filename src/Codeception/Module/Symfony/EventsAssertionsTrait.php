<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\DataCollector\EventDataCollector;
use function array_column;
use function array_merge;
use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function str_starts_with;

trait EventsAssertionsTrait
{
    /**
     * Verifies that **no** events (regular **or** orphan) were dispatched during the test.
     *
     * ```php
     * <?php
     * $I->dontSeeEvent();
     * $I->dontSeeEvent('App\MyEvent');
     * $I->dontSeeEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param class-string|list<class-string>|null $expected Fully-qualified event class(es) that must **not** appear.
     */
    public function dontSeeEvent(array|string|null $expected = null): void
    {
        $actual = $this->collectEvents(orphanOnly: false);
        $this->assertEventTriggered($expected, $actual, shouldExist: false);
    }

    /**
     * Verifies that one or more **listeners** were **not** called during the test.
     *
     * ```php
     * <?php
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener');
     * $I->dontSeeEventListenerIsCalled(['App\MyEventListener', 'App\MyOtherEventListener']);
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener', 'my.event');
     * $I->dontSeeEventListenerIsCalled('App\MyEventListener', ['my.event', 'my.other.event']);
     * ```
     *
     * @param class-string|object|list<class-string|object> $expected Listeners (class-strings or object instances).
     * @param string|list<string>                           $events   Event name(s) (empty = any).
     */
    public function dontSeeEventListenerIsCalled(array|object|string $expected, array|string $events = []): void
    {
        $this->assertListenerCalled($expected, $events, shouldBeCalled: false);
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
     * @param      class-string|object|list<class-string|object> $expected
     * @deprecated Use {@see dontSeeEventListenerIsCalled()} instead.
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
     * {@link https://symfony.com/doc/current/components/event_dispatcher.html#dispatch-the-event dispatch()}
     * method of the EventDispatcher but was not handled by any listener after it was dispatched.
     *
     * ```php
     * <?php
     * $I->dontSeeOrphanEvent();
     * $I->dontSeeOrphanEvent('App\MyEvent');
     * $I->dontSeeOrphanEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param class-string|list<class-string>|null $expected Event class(es) that must **not** appear as orphan.
     */
    public function dontSeeOrphanEvent(array|string|null $expected = null): void
    {
        $actual = $this->collectEvents(orphanOnly: true);
        $this->assertEventTriggered($expected, $actual, shouldExist: false);
    }

    /**
     * Verifies that at least one of the given events **was** dispatched (regular **or** orphan).
     *
     * ```php
     * <?php
     * $I->seeEvent('App\MyEvent');
     * $I->seeEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param class-string|list<class-string> $expected Fully-qualified class-name(s) of the expected event(s).
     */
    public function seeEvent(array|string $expected): void
    {
        $actual = $this->collectEvents(orphanOnly: false);
        $this->assertEventTriggered($expected, $actual, shouldExist: true);
    }

    /**
     * Verifies that one or more **listeners** were called during the test.
     *
     * ```php
     * <?php
     * $I->seeEventListenerIsCalled('App\MyEventListener');
     * $I->seeEventListenerIsCalled(['App\MyEventListener', 'App\MyOtherEventListener']);
     * $I->seeEventListenerIsCalled('App\MyEventListener', 'my.event');
     * $I->seeEventListenerIsCalled('App\MyEventListener', ['my.event', 'my.other.event']);
     * ```
     *
     * @param class-string|object|list<class-string|object> $expected Listeners (class-strings or object instances).
     * @param string|list<string>                           $events   Event name(s) (empty = any).
     */
    public function seeEventListenerIsCalled(array|object|string $expected, array|string $events = []): void
    {
        $this->assertListenerCalled($expected, $events, shouldBeCalled: true);
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
     * @param class-string|object|list<class-string|object> $expected
     * @deprecated Use {@see seeEventListenerIsCalled()} instead.
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
     * Verifies that one or more orphan events **were** dispatched during the test.
     *
     * An orphan event is an event that was triggered by manually executing the
     * {@link https://symfony.com/doc/current/components/event_dispatcher.html#dispatch-the-event dispatch()}
     * method of the EventDispatcher but was not handled by any listener after it was dispatched.
     *
     * ```php
     * <?php
     * $I->seeOrphanEvent('App\MyEvent');
     * $I->seeOrphanEvent(['App\MyEvent', 'App\MyOtherEvent']);
     * ```
     *
     * @param class-string|list<class-string> $expected Event class-name(s) expected to be orphan.
     */
    public function seeOrphanEvent(array|string $expected): void
    {
        $actual = $this->collectEvents(orphanOnly: true);
        $this->assertEventTriggered($expected, $actual, shouldExist: true);
    }

    /** @return list<array{event: string, pretty: string}> */
    protected function getDispatchedEvents(): array
    {
        $eventCollector  = $this->grabEventCollector(__FUNCTION__);
        $calledListeners = $eventCollector->getCalledListeners($this->getDefaultDispatcher());

        /** @var list<array{event: string, pretty: string}> */
        return is_array($calledListeners)
            ? array_values($calledListeners)
            : $calledListeners->getValue(true);
    }

    /** @return list<string> */
    protected function getOrphanedEvents(): array
    {
        $eventCollector = $this->grabEventCollector(__FUNCTION__);
        $orphanedEvents = $eventCollector->getOrphanedEvents($this->getDefaultDispatcher());

        /** @var list<string> */
        return is_array($orphanedEvents)
            ? array_values($orphanedEvents)
            : $orphanedEvents->getValue(true);
    }

    /** @return list<list<string>> */
    private function collectEvents(bool $orphanOnly): array
    {
        return $orphanOnly
            ? [$this->getOrphanedEvents()]
            : [$this->getOrphanedEvents(), array_column($this->getDispatchedEvents(), 'event')];
    }

    /**
     * @param class-string|object|list<class-string|object>|null $expected
     * @param list<list<string>>                                 $actual
     */
    protected function assertEventTriggered(array|object|string|null $expected, array $actual, bool $shouldExist): void
    {
        $actualEvents = array_merge(...$actual);

        if ($shouldExist) {
            $this->assertNotEmpty($actualEvents, 'No event was triggered.');
        }
        if ($expected === null) {
            $this->assertEmpty($actualEvents);
            return;
        }

        $expectedEvents = is_object($expected) ? [$expected] : (array) $expected;
        foreach ($expectedEvents as $expectedEvent) {
            $eventName    = is_object($expectedEvent) ? $expectedEvent::class : $expectedEvent;
            $wasTriggered = in_array($eventName, $actualEvents, true);

            $this->assertSame(
                $shouldExist,
                $wasTriggered,
                sprintf("The '%s' event %s triggered", $eventName, $shouldExist ? 'did not' : 'was')
            );
        }
    }

    /**
     * @param class-string|object|list<class-string|object> $expectedListeners
     * @param string|list<string>                           $expectedEvents
     */
    protected function assertListenerCalled(
        array|object|string $expectedListeners,
        array|string $expectedEvents,
        bool $shouldBeCalled
    ): void {
        $expectedListeners = is_array($expectedListeners) ? $expectedListeners : [$expectedListeners];
        $expectedEvents    = is_array($expectedEvents) ? $expectedEvents : [$expectedEvents];

        if ($expectedEvents === []) {
            $expectedEvents = [null];
        } elseif (count($expectedListeners) > 1) {
            Assert::fail('Cannot check for events when using multiple listeners. Make multiple assertions instead.');
        }

        $actualEvents = $this->getDispatchedEvents();

        if ($shouldBeCalled && $actualEvents === []) {
            Assert::fail('No event listener was called.');
        }

        foreach ($expectedListeners as $expectedListener) {
            $expectedListener = is_string($expectedListener) ? $expectedListener : $expectedListener::class;

            foreach ($expectedEvents as $expectedEvent) {
                $eventName = $expectedEvent ?: null;
                $wasCalled = $this->listenerWasCalled($expectedListener, $eventName, $actualEvents);

                $this->assertSame(
                    $shouldBeCalled,
                    $wasCalled,
                    sprintf(
                        "The '%s' listener was %scalled%s",
                        $expectedListener,
                        $shouldBeCalled ? 'not ' : '',
                        $eventName ? " for the '{$eventName}' event" : ''
                    )
                );
            }
        }
    }

    /** @param list<array{event: string, pretty: string}> $actualEvents */
    private function listenerWasCalled(string $expectedListener, ?string $expectedEvent, array $actualEvents): bool
    {
        foreach ($actualEvents as $actualEvent) {
            if (str_starts_with($actualEvent['pretty'], $expectedListener)
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
        return $this->grabCollector(DataCollectorName::EVENTS, $function);
    }
}

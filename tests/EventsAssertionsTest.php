<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\EventsAssertionsTrait;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\HttpKernel\Kernel;
use Tests\App\Event\TestEvent;
use Tests\App\Listener\TestEventListener;
use Tests\Support\CodeceptTestCase;

final class EventsAssertionsTest extends CodeceptTestCase
{
    use EventsAssertionsTrait;

    public function testDontSeeEvent(): void
    {
        $this->client->request('GET', '/dispatch-orphan-event');
        $this->dontSeeEvent(TestEvent::class);
    }

    public function testDontSeeEventListenerIsCalled(): void
    {
        $this->client->request('GET', '/dispatch-orphan-event');
        $this->dontSeeEventListenerIsCalled(TestEventListener::class);
    }

    #[IgnoreDeprecations]
    public function testDontSeeEventTriggered(): void
    {
        $this->client->request('GET', '/dispatch-orphan-event');
        $this->dontSeeEventTriggered(TestEventListener::class);
    }

    public function testDontSeeOrphanEvent(): void
    {
        if (Kernel::VERSION_ID < 60000) {
            $this->markTestSkipped('Orphan event detection requires Symfony 6.0+');
        }
        $this->client->request('GET', '/dispatch-event');
        $this->dontSeeOrphanEvent();
    }

    public function testSeeEvent(): void
    {
        $this->client->request('GET', '/dispatch-event');
        $this->seeEvent(TestEvent::class);
    }

    public function testSeeEventListenerIsCalled(): void
    {
        $this->client->request('GET', '/dispatch-event');
        $this->seeEventListenerIsCalled(TestEventListener::class, TestEvent::class);

        $this->client->request('GET', '/dispatch-named-event');
        $this->seeEventListenerIsCalled(TestEventListener::class, 'named.event');
    }

    #[IgnoreDeprecations]
    public function testSeeEventTriggered(): void
    {
        $this->client->request('GET', '/dispatch-event');
        $this->seeEventTriggered(TestEventListener::class);
    }

    public function testSeeOrphanEvent(): void
    {
        $this->client->request('GET', '/dispatch-orphan-event');
        $this->seeOrphanEvent('orphan.event');
    }
}

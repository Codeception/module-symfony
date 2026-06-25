<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\MessengerAssertionsTrait;
use stdClass;
use Tests\App\Message\TestMessage;
use Tests\Support\CodeceptTestCase;

final class MessengerAssertionsTest extends CodeceptTestCase
{
    use MessengerAssertionsTrait;

    public function testSeeDispatchedMessageCount(): void
    {
        $this->client->request('GET', '/dispatch-message');

        $this->seeDispatchedMessageCount(1);
        $this->seeDispatchedMessageCount(1, 'messenger.bus.default');
        $this->seeDispatchedMessageCount(0, 'non.existent.bus');
    }

    public function testSeeMessageDispatched(): void
    {
        $this->client->request('GET', '/dispatch-message');

        $this->seeMessageDispatched(TestMessage::class);
        $this->seeMessageDispatched(TestMessage::class, 'messenger.bus.default');
    }

    public function testDontSeeMessageDispatched(): void
    {
        $this->client->request('GET', '/dispatch-message');

        $this->dontSeeMessageDispatched(stdClass::class);
        $this->dontSeeMessageDispatched(TestMessage::class, 'non.existent.bus');
    }

    public function testGrabDispatchedMessageClasses(): void
    {
        $this->client->request('GET', '/dispatch-message');

        $messages = $this->grabDispatchedMessageClasses();

        $this->assertSame([TestMessage::class], $messages);
        $this->assertSame([TestMessage::class], $this->grabDispatchedMessageClasses('messenger.bus.default'));
        $this->assertSame([], $this->grabDispatchedMessageClasses('non.existent.bus'));
    }

    public function testNoMessagesDispatched(): void
    {
        $this->client->request('GET', '/');

        $this->seeDispatchedMessageCount(0);
        $this->assertSame([], $this->grabDispatchedMessageClasses());
    }
}

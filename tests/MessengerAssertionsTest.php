<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\MessengerAssertionsTrait;
use stdClass;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Tests\App\Message\TestMessage;
use Tests\App\MessageHandler\TestMessageHandler;
use Tests\Support\CodeceptTestCase;

use function class_exists;

final class MessengerAssertionsTest extends CodeceptTestCase
{
    use MessengerAssertionsTrait;

    public function testConsumeMessengerMessages(): void
    {
        $this->requireInMemoryTransport();
        $this->client->request('GET', '/dispatch-message');
        $this->seeMessengerQueueCount(1, 'async');

        $this->consumeMessengerMessages('async');

        $this->seeMessengerQueueCount(0, 'async');

        $handler = $this->grabService(TestMessageHandler::class);
        $this->assertContains('Hello from Messenger', $handler->handled);
    }

    public function testGrabMessengerTransport(): void
    {
        $this->requireInMemoryTransport();
        $this->client->request('GET', '/dispatch-message');

        $sent = $this->grabMessengerTransport('async')->getSent();

        $this->assertCount(1, $sent);
        $this->assertInstanceOf(TestMessage::class, $sent[0]->getMessage());
    }

    public function testSeeMessengerQueueCount(): void
    {
        $this->requireInMemoryTransport();
        $this->client->request('GET', '/dispatch-message');

        $this->seeMessengerQueueCount(1, 'async');
    }

    public function testSeeMessengerTransportContains(): void
    {
        $this->requireInMemoryTransport();
        $this->client->request('GET', '/dispatch-message');

        $this->seeMessengerTransportContains(TestMessage::class, 'async');
    }

    private function requireInMemoryTransport(): void
    {
        if (!class_exists(InMemoryTransport::class)) {
            $this->markTestSkipped('symfony/messenger 6.3 or higher (in-memory transport) is required.');
        }
    }

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

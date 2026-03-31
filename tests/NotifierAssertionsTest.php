<?php

declare(strict_types=1);

namespace Tests;

use Codeception\Module\Symfony\NotifierAssertionsTrait;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Message\ChatMessage;
use Tests\App\Notifier\NotifierFixture;
use Tests\Support\CodeceptTestCase;

final class NotifierAssertionsTest extends CodeceptTestCase
{
    use NotifierAssertionsTrait;

    protected function setUp(): void
    {
        parent::setUp();
        if (Kernel::VERSION_ID < 60200) {
            $this->markTestSkipped('Notifier assertions require Symfony 6.2+');
        }
        $this->grabService('notifier.notification_logger_listener')->reset();
    }

    public function testAssertNotificationCount(): void
    {
        $this->sendNotifications();
        $this->assertNotificationCount(1);
        $this->assertNotificationCount(1, 'primary');
    }

    public function testAssertNotificationIsNotQueued(): void
    {
        $this->assertNotificationIsNotQueued($this->sendNotifications()['sent']);
    }

    public function testAssertNotificationIsQueued(): void
    {
        $this->assertNotificationIsQueued($this->sendNotifications()['queued']);
    }

    public function testAssertNotificationSubjectContains(): void
    {
        $this->sendNotifications();
        $this->assertNotificationSubjectContains($this->getNotifierMessage(), 'Welcome');
    }

    public function testAssertNotificationSubjectNotContains(): void
    {
        $this->sendNotifications();
        $this->assertNotificationSubjectNotContains($this->getNotifierMessage(), 'missing');
    }

    public function testAssertNotificationTransportIsEqual(): void
    {
        $this->sendNotifications();
        $this->grabLastSentNotification();
        $this->grabService(NotifierFixture::class)->sendNotification('Primary alert', 'chat');
        $this->assertNotificationTransportIsEqual($this->grabLastSentNotification(), 'chat');
    }

    public function testAssertNotificationTransportIsNotEqual(): void
    {
        $this->grabService(NotifierFixture::class)->sendNotification('Primary alert', 'chat');
        $this->assertNotificationTransportIsNotEqual($this->grabLastSentNotification(), 'email');
    }

    public function testAssertQueuedNotificationCount(): void
    {
        $this->sendNotifications();
        $this->assertQueuedNotificationCount(1);
        $this->assertQueuedNotificationCount(1, 'queued');
    }

    public function testDontSeeNotificationIsSent(): void
    {
        $this->dontSeeNotificationIsSent();
    }

    public function testGetNotifierEvent(): void
    {
        $this->sendNotifications();
        $this->assertInstanceOf(MessageEvent::class, $this->getNotifierEvent());
    }

    public function testGetNotifierEvents(): void
    {
        $this->sendNotifications();
        $this->assertCount(2, $this->getNotifierEvents());
    }

    public function testGetNotifierMessage(): void
    {
        $this->sendNotifications();
        $this->assertInstanceOf(ChatMessage::class, $this->getNotifierMessage());
    }

    public function testGetNotifierMessages(): void
    {
        $this->sendNotifications();
        $this->assertCount(2, $this->getNotifierMessages());
    }

    public function testGrabLastSentNotification(): void
    {
        $this->grabService(NotifierFixture::class)->sendNotification('Last One', 'chat');
        $last = $this->grabLastSentNotification();
        $this->assertInstanceOf(ChatMessage::class, $last);
        $this->assertSame('Last One', $last->getSubject());
    }

    public function testGrabSentNotifications(): void
    {
        $this->sendNotifications();
        $this->assertCount(2, $this->grabSentNotifications());
    }

    public function testSeeNotificationIsSent(): void
    {
        $this->sendNotifications();
        $this->seeNotificationIsSent();
    }

    public function testEdgeCases(): void
    {
        // No notifications sent
        $this->assertNull($this->grabLastSentNotification());

        // Out of range index
        $this->sendNotifications();
        $this->assertNull($this->getNotifierEvent(999));
        $this->assertNull($this->getNotifierMessage(999));
    }

    private function sendNotifications(): array
    {
        $fixture = $this->grabService(NotifierFixture::class);
        return [
            'sent' => $fixture->sendNotification('Welcome notification', 'primary'),
            'queued' => $fixture->sendNotification('Queued notification', 'queued', true),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\NotificationEvents;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Test\Constraint\NotificationCount;
use Symfony\Component\Notifier\Test\Constraint\NotificationIsQueued;
use Symfony\Component\Notifier\Test\Constraint\NotificationSubjectContains;
use Symfony\Component\Notifier\Test\Constraint\NotificationTransportIsEqual;

trait NotificationAssertionsTrait
{
    /**
     * Asserts that the given number of notifications has been created (in total or for the given transport).
     */
    public function assertNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($this->getNotificationEvents(), new NotificationCount($count, $transportName), $message);
    }

    /**
     * Asserts that the given notification is not queued.
     */
    public function assertNotificationIsNotQueued(MessageEvent $event, string $message = ''): void
    {
        $this->assertThat($event, new LogicalNot(new NotificationIsQueued()), $message);
    }

    /**
     * Asserts that the given notification is queued.
     */
    public function assertNotificationIsQueued(MessageEvent $event, string $message = ''): void
    {
        $this->assertThat($event, new NotificationIsQueued(), $message);
    }

    /**
     * Asserts that the given text is included in the subject of the given notification.
     */
    public function assertNotificationSubjectContains(MessageInterface $notification, string $text, string $message = ''): void
    {
        $this->assertThat($notification, new NotificationSubjectContains($text), $message);
    }

    /**
     * Asserts that the given text is not included in the subject of the given notification.
     */
    public function assertNotificationSubjectNotContains(MessageInterface $notification, string $text, string $message = ''): void
    {
        $this->assertThat($notification, new LogicalNot(new NotificationSubjectContains($text)), $message);
    }

    /**
     * Asserts that the name of the transport for the given notification is the same as the given text.
     */
    public function assertNotificationTransportIsEqual(MessageInterface $notification, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($notification, new NotificationTransportIsEqual($transportName), $message);
    }

    /**
     * Asserts that the name of the transport for the given notification is not the same as the given text.
     */
    public function assertNotificationTransportIsNotEqual(MessageInterface $notification, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($notification, new LogicalNot(new NotificationTransportIsEqual($transportName)), $message);
    }

    /**
     * Asserts that the given number of notifications are queued (in total or for the given transport).
     */
    public function assertQueuedNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($this->getNotificationEvents(), new NotificationCount($count, $transportName, true), $message);
    }

    protected function getNotificationEvents(): NotificationEvents
    {
        $notificationLogger = $this->getService('notifier.notification_logger_listener');
        if ($notificationLogger) {
            return $notificationLogger->getEvents();
        }

        $this->fail('A client must have Notifier enabled to make notifications assertions. Did you forget to require symfony/notifier?');
    }
}

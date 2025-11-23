<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\NotificationEvents;
use Symfony\Component\Notifier\EventListener\NotificationLoggerListener;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Test\Constraint as NotifierConstraint;
use Symfony\Component\HttpKernel\Kernel;

trait NotifierAssertionsTrait
{
    /**
     * Asserts that the expected number of notifications was sent.
     *
     * ```php
     * <?php
     * $I->assertNotificationCount(2, 'smtp');
     * ```
     */
    public function assertNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($this->getNotificationEvents(), new NotifierConstraint\NotificationCount($count, $transportName), $message);
    }

    /**
     * Asserts that the given notifier event is not queued.
     * Use `getNotifierEvent(int $index = 0, ?string $transportName = null)` to retrieve a notifier event by index.
     *
     * ```php
     * <?php
     * $event = $I->getNotifierEvent();
     * $I->asserNotificationIsNotQueued($event);
     * ```
     */
    public function assertNotificationIsNotQueued(MessageEvent $event, string $message = ''): void
    {
        $this->assertThat($event, new LogicalNot(new NotifierConstraint\NotificationIsQueued()), $message);
    }

    /**
     * Asserts that the given notifier event is queued.
     * Use `getNotifierEvent(int $index = 0, ?string $transportName = null)` to retrieve a notifier event by index.
     *
     * ```php
     * <?php
     * $event = $I->getNotifierEvent();
     * $I->assertNotificationlIsQueued($event);
     * ```
     */
    public function assertNotificationIsQueued(MessageEvent $event, string $message = ''): void
    {
        $this->assertThat($event, new NotifierConstraint\NotificationIsQueued(), $message);
    }

    /**
     * Asserts that the given notification contains given subject.
     * Use `getNotifierMessage(int $index = 0, ?string $transportName = null)` to retrieve a notification by index.
     *
     * ```php
     * <?php
     * $notification = $I->getNotifierMessage();
     * $I->assertNotificationSubjectContains($notification, 'Subject');
     * ```
     */
    public function assertNotificationSubjectContains(MessageInterface $notification, string $text, string $message = ''): void
    {
        $this->assertThat($notification, new NotifierConstraint\NotificationSubjectContains($text), $message);
    }

    /**
     * Asserts that the given notification does not contain given subject.
     * Use `getNotifierMessage(int $index = 0, ?string $transportName = null)` to retrieve a notification by index.
     *
     * ```php
     * <?php
     * $notification = $I->getNotifierMessage();
     * $I->assertNotificationSubjectNotContains($notification, 'Subject');
     * ```
     */
    public function assertNotificationSubjectNotContains(MessageInterface $notification, string $text, string $message = ''): void
    {
        $this->assertThat($notification, new LogicalNot(new NotifierConstraint\NotificationSubjectContains($text)), $message);
    }

    /**
     * Asserts that the given notification uses given transport.
     * Use `getNotifierMessage(int $index = 0, ?string $transportName = null)` to retrieve a notification by index.
     *
     * ```php
     * <?php
     * $notification = $I->getNotifierMessage();
     * $I->assertNotificationTransportIsEqual($notification, 'chat');
     * ```
     */
    public function assertNotificationTransportIsEqual(MessageInterface $notification, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($notification, new NotifierConstraint\NotificationTransportIsEqual($transportName), $message);
    }

    /**
     * Asserts that the given notification does not use given transport.
     * Use `getNotifierMessage(int $index = 0, ?string $transportName = null)` to retrieve a notification by index.
     *
     * ```php
     * <?php
     * $notification = $I->getNotifierMessage();
     * $I->assertNotificationTransportIsNotEqual($notification, 'transport');
     * ```
     */
    public function assertNotificationTransportIsNotEqual(MessageInterface $notification, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($notification, new LogicalNot(new NotifierConstraint\NotificationTransportIsEqual($transportName)), $message);
    }

    /**
     * Asserts that the expected number of notifications was queued (e.g. using the Notifier component).
     *
     * ```php
     * <?php
     * $I->assertQueuedNotificationCount(1, 'smtp');
     * ```
     */
    public function assertQueuedNotificationCount(int $count, ?string $transportName = null, string $message = ''): void
    {
        $this->assertThat($this->getNotificationEvents(), new NotifierConstraint\NotificationCount($count, $transportName, true), $message);
    }

    /**
     * Checks that no notification was sent.
     * The check is based on `\Symfony\Component\Notifier\EventListener\NotificationLoggerListener`, which means:
     * If your app performs an HTTP redirect, you need to suppress it using [stopFollowingRedirects()](#stopFollowingRedirects) first; otherwise this check will *always* pass.
     *
     * ```php
     * <?php
     * $I->dontSeeNotificationIsSent();
     * ```
     */
    public function dontSeeNotificationIsSent(): void
    {
        $this->assertThat($this->getNotificationEvents(), new NotifierConstraint\NotificationCount(0));
    }

    /**
     * Returns the last sent notification.
     * The check is based on `\Symfony\Component\Notifier\EventListener\NotificationLoggerListener`, which means:
     * If your app performs an HTTP redirect after sending the notification, you need to suppress it using [stopFollowingRedirects()](#stopFollowingRedirects) first.
     * See also: [grabSentNotifications()](https://codeception.com/docs/modules/Symfony#grabSentNotifications)
     *
     * ```php
     * <?php
     * $message = $I->grabLastSentNotification();
     * $I->assertSame('Subject', $message->getSubject());
     * ```
     */
    public function grabLastSentNotification(): ?MessageInterface
    {
        $notification = $this->getNotifierMessages();
        $lastNotification = end($notification);

        return $lastNotification ?: null;
    }


    /**
     * Returns an array of all sent notifications.
     * The check is based on `\Symfony\Component\Notifier\EventListener\NotificationLoggerListener`, which means:
     * If your app performs an HTTP redirect after sending the notification, you need to suppress it using [stopFollowingRedirects()](#stopFollowingRedirects) first.
     * See also: [grabLastSentNotification()](https://codeception.com/docs/modules/Symfony#grabLastSentNotification)
     *
     * ```php
     * <?php
     * $notifications = $I->grabSentNotifications();
     * ```
     *
     * @return MessageInterface[]
     */
    public function grabSentNotifications(): array
    {
        return $this->getNotifierMessages();
    }

    /**
     * Checks if the given number of notifications was sent (default `$expectedCount`: 1).
     * The check is based on `\Symfony\Component\Notifier\EventListener\NotificationLoggerListener`, which means:
     * If your app performs an HTTP redirect after sending the notification, you need to suppress it using [stopFollowingRedirects()](#stopFollowingRedirects) first.
     *
     * ```php
     * <?php
     * $I->seeNotificatoinIsSent(2);
     * ```
     *
     * @param int $expectedCount The expected number of notifications sent
     */
    public function seeNotificationIsSent(int $expectedCount = 1): void
    {
        $this->assertThat($this->getNotificationEvents(), new NotifierConstraint\NotificationCount($expectedCount));
    }

    /**
     * @return MessageEvent[]
     */
    public function getNotifierEvents(?string $transportName = null): array
    {
        return $this->getNotificationEvents()->getEvents($transportName);
    }

    /**
     * Returns the notifier event at the specified index.
     *
     * ```php
     * <?php
     * $event = $I->getNotifierEvent();
     * ```
     */
    public function getNotifierEvent(int $index = 0, ?string $transportName = null): ?MessageEvent
    {
        return $this->getNotifierEvents($transportName)[$index] ?? null;
    }

    /**
     * @return MessageInterface[]
     */
    public function getNotifierMessages(?string $transportName = null): array
    {
        return $this->getNotificationEvents()->getMessages($transportName);
    }

    /**
     * Returns the notifier message at the specified index.
     *
     * ```php
     * <?php
     * $message = $I->getNotifierMessage();
     * ```
     */
    public function getNotifierMessage(int $index = 0, ?string $transportName = null): ?MessageInterface
    {
        return $this->getNotifierMessages($transportName)[$index] ?? null;
    }

    protected function getNotificationEvents(): NotificationEvents
    {
        // @phpstan-ignore if.alwaysFalse
        if (version_compare(Kernel::VERSION, '6.2', '<')) {
            Assert::fail('Notifier assertions require Symfony 6.2 or higher.');
        }

        $services = ['notifier.notification_logger_listener', 'notifier.logger_notification_listener'];
        foreach ($services as $serviceId) {
            $notifier = $this->getService($serviceId);
            if ($notifier instanceof NotificationLoggerListener) {
                return $notifier->getEvents();
            }
        }
        Assert::fail("Notifications can't be tested without Symfony Notifier service.");
    }
}

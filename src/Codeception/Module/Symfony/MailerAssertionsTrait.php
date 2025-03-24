<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use PHPUnit\Framework\Constraint\LogicalNot;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mailer\Test\Constraint as MailerConstraint;
use Symfony\Component\Mime\Email;

trait MailerAssertionsTrait
{
    /**
     * Asserts that the expected number of emails was sent.
     *
     * ```php
     * <?php
     * $I->assertEmailCount(2, 'smtp');
     * ```
     */
    public function assertEmailCount(int $count, ?string $transport = null, string $message = ''): void
    {
        $this->assertThat($this->getMessageMailerEvents(), new MailerConstraint\EmailCount($count, $transport), $message);
    }

    /**
     * Asserts that the given mailer event is not queued.
     * Use `getMailerEvent(int $index = 0, ?string $transport = null)` to retrieve a mailer event by index.
     *
     * ```php
     * <?php
     * $event = $I->getMailerEvent();
     * $I->assertEmailIsNotQueued($event);
     * ```
     */
    public function assertEmailIsNotQueued(MessageEvent $event, string $message = ''): void
    {
        $this->assertThat($event, new LogicalNot(new MailerConstraint\EmailIsQueued()), $message);
    }

    /**
     * Asserts that the given mailer event is queued.
     * Use `getMailerEvent(int $index = 0, ?string $transport = null)` to retrieve a mailer event by index.
     *
     * ```php
     * <?php
     * $event = $I->getMailerEvent();
     * $I->assertEmailIsQueued($event);
     * ```
     */
    public function assertEmailIsQueued(MessageEvent $event, string $message = ''): void
    {
        $this->assertThat($event, new MailerConstraint\EmailIsQueued(), $message);
    }

    /**
     * Asserts that the expected number of emails was queued (e.g. using the Messenger component).
     *
     * ```php
     * <?php
     * $I->assertQueuedEmailCount(1, 'smtp');
     * ```
     */
    public function assertQueuedEmailCount(int $count, ?string $transport = null, string $message = ''): void
    {
        $this->assertThat($this->getMessageMailerEvents(), new MailerConstraint\EmailCount($count, $transport, true), $message);
    }

    /**
     * Checks that no email was sent.
     * The check is based on `\Symfony\Component\Mailer\EventListener\MessageLoggerListener`, which means:
     * If your app performs an HTTP redirect, you need to suppress it using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first;
     * otherwise this check will *always* pass.
     *
     * ```php
     * <?php
     * $I->dontSeeEmailIsSent();
     * ```
     */
    public function dontSeeEmailIsSent(): void
    {
        $this->assertThat($this->getMessageMailerEvents(), new MailerConstraint\EmailCount(0));
    }

    /**
     * Returns the last sent email.
     * The function is based on `\Symfony\Component\Mailer\EventListener\MessageLoggerListener`, which means:
     * If your app performs an HTTP redirect after sending the email, you need to suppress it using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first.
     * See also: [grabSentEmails()](https://codeception.com/docs/modules/Symfony#grabSentEmails)
     *
     * ```php
     * <?php
     * $email = $I->grabLastSentEmail();
     * $address = $email->getTo()[0];
     * $I->assertSame('john_doe@example.com', $address->getAddress());
     * ```
     */
    public function grabLastSentEmail(): ?Email
    {
        /** @var Email[] $emails */
        $emails = $this->getMessageMailerEvents()->getMessages();
        $lastEmail = end($emails);

        return $lastEmail ?: null;
    }

    /**
     * Returns an array of all sent emails.
     * The function is based on `\Symfony\Component\Mailer\EventListener\MessageLoggerListener`, which means:
     * If your app performs an HTTP redirect after sending the email, you need to suppress it using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first.
     * See also: [grabLastSentEmail()](https://codeception.com/docs/modules/Symfony#grabLastSentEmail)
     *
     * ```php
     * <?php
     * $emails = $I->grabSentEmails();
     * ```
     *
     * @return \Symfony\Component\Mime\Email[]
     */
    public function grabSentEmails(): array
    {
        return $this->getMessageMailerEvents()->getMessages();
    }

    /**
     * Checks if the given number of emails was sent (default `$expectedCount`: 1).
     * The check is based on `\Symfony\Component\Mailer\EventListener\MessageLoggerListener`, which means:
     * If your app performs an HTTP redirect after sending the email, you need to suppress it using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first.
     *
     * ```php
     * <?php
     * $I->seeEmailIsSent(2);
     * ```
     *
     * @param int $expectedCount The expected number of emails sent
     */
    public function seeEmailIsSent(int $expectedCount = 1): void
    {
        $this->assertThat($this->getMessageMailerEvents(), new MailerConstraint\EmailCount($expectedCount));
    }

    /**
     * Returns the mailer event at the specified index.
     *
     * ```php
     * <?php
     * $event = $I->getMailerEvent();
     * ```
     */
    public function getMailerEvent(int $index = 0, ?string $transport = null): ?MessageEvent
    {
        $mailerEvents = $this->getMessageMailerEvents();
        $events = $mailerEvents->getEvents($transport);
        return $events[$index] ?? null;
    }

    protected function getMessageMailerEvents(): MessageEvents
    {
        if ($mailer = $this->getService('mailer.message_logger_listener')) {
            /** @var MessageLoggerListener $mailer */
            return $mailer->getEvents();
        }
        if ($mailer = $this->getService('mailer.logger_message_listener')) {
            /** @var MessageLoggerListener $mailer */
            return $mailer->getEvents();
        }
        $this->fail("Emails can't be tested without Symfony Mailer service.");
    }
}

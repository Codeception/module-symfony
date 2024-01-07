<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mailer\Test\Constraint as MailerConstraint;
use Symfony\Component\Mime\Email;

trait MailerAssertionsTrait
{
    /**
     * Checks that no email was sent.
     * The check is based on `\Symfony\Component\Mailer\EventListener\MessageLoggerListener`, which means:
     * If your app performs an HTTP redirect, you need to suppress it using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first; otherwise this check will *always* pass.
     */
    public function dontSeeEmailIsSent(): void
    {
        $this->assertThat($this->getMessageMailerEvents(), new MailerConstraint\EmailCount(0));
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

    protected function getMessageMailerEvents(): MessageEvents
    {
        if ($messageLogger = $this->getService('mailer.message_logger_listener')) {
            /** @var MessageLoggerListener $messageLogger */
            return $messageLogger->getEvents();
        }

        if ($messageLogger = $this->getService('mailer.logger_message_listener')) {
            /** @var MessageLoggerListener $messageLogger */
            return $messageLogger->getEvents();
        }

        $this->fail("Emails can't be tested without Symfony Mailer service.");
    }
}

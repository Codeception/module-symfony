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
     */
    public function dontSeeEmailIsSent(): void
    {
        $this->assertThat($this->getMessageMailerEvents(), new MailerConstraint\EmailCount(0));
    }

    /**
     * Checks if the given number of emails was sent (default `$expectedCount`: 1).
     * The check is based on `\Symfony\Component\Mailer\EventListener\MessageLoggerListener`, which means:
     * If your app performs a HTTP redirect after sending the email, you need to suppress it using [stopFollowingRedirects()](https://codeception.com/docs/modules/Symfony#stopFollowingRedirects) first.
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
     *
     * ```php
     * <?php
     * $email = $I->grabLastSentEmail();
     * $address = $email->getTo()[0];
     * $I->assertSame('john_doe@user.com', $address->getAddress());
     * ```
     *
     * @return \Symfony\Component\Mime\Email|null
     */
    public function grabLastSentEmail(): ?Email
    {
        $emails = $this->getMessageMailerEvents()->getMessages();
        /** @var Email|false $lastEmail */
        if ($lastEmail = end($emails)) {
            return $lastEmail;
        }
        return null;
    }

    /**
     * Returns an array of all sent emails.
     *
     * ```php
     * <?php
     * $emails = $I->grabSentEmails();
     * $address = $emails[0]->getTo()[0];
     * $I->assertSame('john_doe@user.com', $address->getAddress());
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

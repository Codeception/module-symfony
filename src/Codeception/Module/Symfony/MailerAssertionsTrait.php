<?php

declare(strict_types=1);

namespace Codeception\Module\Symfony;

use Symfony\Component\Mailer\Event\MessageEvents;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mailer\Test\Constraint as MailerConstraint;

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
     * Checks if the desired number of emails was sent.
     * Asserts that 1 email was sent by default, specify the `expectedCount` parameter to modify it.
     * The email is checked using Symfony message logger, which means:
     * * If your app performs a redirect after sending the email, you need to suppress this using REST Module's [stopFollowingRedirects](https://codeception.com/docs/modules/REST#stopFollowingRedirects)
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

    protected function getMessageMailerEvents(): MessageEvents
    {
        $container = $this->_getContainer();

        if ($container->has('mailer.message_logger_listener')) {
            /** @var MessageLoggerListener $messageLogger */
            $messageLogger = $container->get('mailer.message_logger_listener');
            return $messageLogger->getEvents();
        }

        if ($container->has('mailer.logger_message_listener')) {
            /** @var MessageLoggerListener $messageLogger */
            $messageLogger = $container->get('mailer.logger_message_listener');
            return $messageLogger->getEvents();
        }

        $this->fail("Emails can't be tested without Symfony Mailer service.");
    }
}